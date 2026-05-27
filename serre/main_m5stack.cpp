/**
 * @file    gateway.cpp
 * @brief   Gateway ESP32 — reçoit les mesures des nœuds capteurs via ESP-NOW
 *          et les publie sur un broker MQTT (TLS) sous le topic serre/X/bac/Y.
 *
 * Flux de données :
 *   Nœud air/sol  --[ESP-NOW]-->  onDataRecv()  --[Queue FreeRTOS]-->  loop()  --[MQTT]-->  Broker
 */

#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <PubSubClient.h>
#include <freertos/queue.h>
#include "secrets.h"

#define SERRE_ID        1            ///< Identifiant logique de la serre
#define BAC_ID          1            ///< Identifiant logique du bac dans la serre
#define ESPNOW_CHANNEL  1            ///< Canal Wi-Fi fixe partagé avec les nœuds

/**
 * @brief Paquet ESP-NOW reçu depuis un nœud capteur.
 *        Attribut packed : pas de padding, taille garantie identique
 *        côté émetteur et récepteur.
 */
typedef struct __attribute__((packed)) {
  char  type[4];       ///< "air\0" ou "sol\0"
  float temperature;   ///< Température en °C  (non utilisée pour le sol)
  float humidity;      ///< Humidité en %
} SensorData;

/**
 * @brief Instantané cohérent des 3 grandeurs d'un bac, prêt à être publié.
 *        Stocké dans la queue FreeRTOS en attendant que MQTT soit disponible.
 */
typedef struct {
  float temperatureAmbiante;  ///< Dernière température air reçue
  float humiditeAmbiante;     ///< Dernière humidité air reçue
  float humiditeSol;          ///< Dernière humidité sol reçue
} SensorSnapshot;

WiFiClientSecure espClient;           ///< Socket TLS vers le broker
PubSubClient     mqttClient(espClient);
QueueHandle_t    sensorQueue;         ///< Queue FreeRTOS 

// Dernières valeurs reçues ; -999 = jamais reçu
volatile float lastTempAmbiante = -999.0f;
volatile float lastHumAmbiante  = -999.0f;
volatile float lastHumSol       = -999.0f;

unsigned long lastMqttRetry    = 0;
unsigned long lastHeartbeat    = 0;
unsigned long lastChannelCheck = 0;
unsigned long mqttBackoff      = 5000UL;  

String clientId;  ///< Identifiant MQTT unique, basé sur le MAC de l'ESP

void syncNTP();
void connectWiFi();
void setupESPNow();
void checkChannel();
void publishAll(const SensorSnapshot& snap);
void tryConnectMQTT();


/**
 * @brief Publie l'instantané des 3 capteurs sur le topic MQTT du bac.
 *
 * La publication n'a lieu que si les 3 valeurs ont été reçues au moins une fois
 * (seuil -100). Si l'une est manquante, la fonction log et retourne sans publier.
 *
 * Topic   : serre/<SERRE_ID>/bac/<BAC_ID>
 * Payload : {"temperatureAmbiante":X.X,"humiditeAmbiante":X.X,"humiditeSol":X.X}
 *
 * @param snap  Instantané à publier.
 */
void publishAll(const SensorSnapshot& snap) {
  if (snap.temperatureAmbiante < -100.0f ||
      snap.humiditeAmbiante    < -100.0f ||
      snap.humiditeSol         < -100.0f) {
    Serial.println("[MQTT] En attente : valeurs incompletes (air + sol requis)");
    return;
  }

  char topic[48];
  char payload[128];
  snprintf(topic, sizeof(topic), "serre/%d/bac/%d", SERRE_ID, BAC_ID);
  snprintf(payload, sizeof(payload),
           "{\"temperatureAmbiante\":%.1f,\"humiditeAmbiante\":%.1f,\"humiditeSol\":%.1f}",
           snap.temperatureAmbiante, snap.humiditeAmbiante, snap.humiditeSol);

  bool ok = mqttClient.publish(topic, payload);
  Serial.printf("[MQTT] %s -> %s [%s]\n", topic, payload, ok ? "PUB OK" : "FAIL");
}

/**
 * @brief Tente (ré)établir la connexion MQTT avec backoff exponentiel.
 *
 * - Respecte le délai `mqttBackoff` entre chaque tentative.
 * - Vérifie que l'heure NTP est synchronisée avant le handshake TLS
 *   (un certificat avec une date invalide serait rejeté).
 * - En cas d'échec, double le backoff jusqu'à 60 s.
 * - En cas de succès, remet le backoff à 5 s.
 */
void tryConnectMQTT() {
  if (millis() - lastMqttRetry < mqttBackoff) return;
  lastMqttRetry = millis();

  Serial.printf("[MQTT] Heap avant connexion : %u octets | Backoff : %lus\n",
                esp_get_free_heap_size(), mqttBackoff / 1000);

  struct tm timeinfo;
  if (!getLocalTime(&timeinfo)) {
    Serial.println("[MQTT] ATTENTION : Heure non synchronisee, TLS risque d'echouer !");
    syncNTP();
    return;
  }

  Serial.printf("[MQTT] Connexion a %s:%d ...\n", MQTT_SERVER, MQTT_PORT);

  if (mqttClient.connect(clientId.c_str())) {
    Serial.println("[MQTT] Connecte !");
    mqttBackoff = 5000UL;  
  } else {
    int rc = mqttClient.state();
    Serial.printf("[MQTT] Echec rc=%d | ", rc);
    switch (rc) {
      case -2: Serial.print("Connexion refusee (check TLS/heap/port)"); break;
      case -3: Serial.print("Deconnecte"); break;
      case -4: Serial.print("Timeout serveur"); break;
      case  5: Serial.print("Non autorise (username/mot de passe ou certificat rejete)"); break;
      default: Serial.print("Erreur inconnue"); break;
    }
    Serial.printf(" | Prochain essai dans %lus\n", mqttBackoff / 1000);
    mqttBackoff = min(mqttBackoff * 2, 60000UL);   
  }
}

/**
 * @brief Vérifie que le canal Wi-Fi n'a pas dérivé et le corrige si nécessaire.
 *
 * Le driver Wi-Fi peut changer de canal lorsque le firmware gère le roaming
 * ou reçoit des trames de gestion. ESP-NOW exige un canal fixe commun avec
 * les nœuds ; cette fonction est appelée toutes les 30 s depuis loop().
 */
void checkChannel() {
  uint8_t primaryChan;
  wifi_second_chan_t secondChan;
  esp_wifi_get_channel(&primaryChan, &secondChan);
  if (primaryChan != ESPNOW_CHANNEL) {
    Serial.printf("[WARN] Canal derive sur %d, correction sur %d...\n",
                  primaryChan, ESPNOW_CHANNEL);
    // Mode promiscuous requis pour forcer le canal sans être associé à un AP
    esp_wifi_set_promiscuous(true);
    esp_wifi_set_channel(ESPNOW_CHANNEL, WIFI_SECOND_CHAN_NONE);
    esp_wifi_set_promiscuous(false);
  }
}

/**
 * @brief Callback ESP-NOW appelé à chaque réception d'un paquet.
 *
 * Exécutée dans le contexte de la tâche Wi-Fi (ISR-like) ; ne doit pas
 * bloquer. Met à jour les variables globales lastTemp/lastHum et place un
 * SensorSnapshot dans la queue FreeRTOS pour traitement dans loop().
 *
 * @param mac_addr  Adresse MAC de l'émetteur (non utilisée ici).
 * @param data      Pointeur sur les octets reçus.
 * @param len       Longueur du payload reçu.
 */
void onDataRecv(const uint8_t* mac_addr, const uint8_t* data, int len) {
  if (len != sizeof(SensorData)) {
    Serial.printf("[ESP-NOW] Taille inattendue : %d octets (attendu %d)\n",
                  len, (int)sizeof(SensorData));
    return;
  }

  SensorData received;
  memcpy(&received, data, sizeof(received));
  received.type[3] = '\0';   

  if (strncmp(received.type, "air", 3) == 0) {
    lastTempAmbiante = received.temperature;
    lastHumAmbiante  = received.humidity;
    Serial.printf("[ESP-NOW] air -> T:%.1f°C H:%.1f%%\n",
                  received.temperature, received.humidity);
  } else if (strncmp(received.type, "sol", 3) == 0) {
    lastHumSol = received.humidity;
    Serial.printf("[ESP-NOW] sol -> Sol:%.1f%%\n", received.humidity);
  } else {
    Serial.printf("[ESP-NOW] Type inconnu : %.3s\n", received.type);
    return;
  }

  SensorSnapshot snap = { lastTempAmbiante, lastHumAmbiante, lastHumSol };
  if (xQueueSend(sensorQueue, &snap, 0) != pdTRUE) {
    Serial.println("[WARN] Queue pleine, mesure ignoree");
  }
}

/**
 * @brief Connecte le module au réseau Wi-Fi en mode station.
 *
 * Bloquant jusqu'à l'association. Désactive la mise en veille Wi-Fi
 * pour éviter les latences de réveil qui perturbent ESP-NOW.
 */
void connectWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("[WiFi] Connexion");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  WiFi.setSleep(false);   
  Serial.printf("\n[WiFi] OK | IP: %s | Canal: %d\n",
                WiFi.localIP().toString().c_str(), WiFi.channel());
}

/**
 * @brief Synchronise l'horloge interne via NTP et configure le fuseau Europe/Paris.
 *
 * Indispensable avant tout handshake TLS : la validation du certificat serveur
 * compare la date courante aux champs notBefore / notAfter du certificat.
 * Attend au maximum 40 × 500 ms = 20 s avant de déclarer un timeout.
 */
void syncNTP() {
  configTime(0, 0, NTP_SERVER);
  setenv("TZ", "CET-1CEST,M3.5.0,M10.5.0/3", 1);
  tzset();

  struct tm timeinfo;
  int retries = 0;
  Serial.print("[NTP] Sync");
  while (!getLocalTime(&timeinfo) && retries < 40) {
    delay(500);
    Serial.print(".");
    retries++;
  }
  Serial.println();
  if (retries < 40) Serial.println("[NTP] OK");
  else              Serial.println("[NTP] TIMEOUT — le handshake TLS risque d'echouer !");
}

/**
 * @brief Initialise ESP-NOW en mode récepteur sur le canal fixe ESPNOW_CHANNEL.
 *
 * Le mode promiscuous est activé brièvement pour pouvoir imposer le canal
 * sans être associé à un point d'accès sur ce canal.
 * Enregistre onDataRecv() comme callback de réception.
 */
void setupESPNow() {
  esp_wifi_set_promiscuous(true);
  esp_wifi_set_channel(ESPNOW_CHANNEL, WIFI_SECOND_CHAN_NONE);
  esp_wifi_set_promiscuous(false);

  if (esp_now_init() != ESP_OK) {
    Serial.println("[ESP-NOW] Erreur init !");
    return;
  }
  esp_now_register_recv_cb(onDataRecv);
  Serial.println("[ESP-NOW] Gateway prete, en ecoute...");
}


void setup() {
  Serial.begin(115200);

  // Queue de 10 snapshots ; si MQTT est down on garde les mesures en mémoire
  sensorQueue = xQueueCreate(10, sizeof(SensorSnapshot));
  clientId    = "M5-Gateway-" + String((uint32_t)ESP.getEfuseMac(), HEX);

  connectWiFi();
  syncNTP();

  struct tm timeinfo;
  if (getLocalTime(&timeinfo)) {
    Serial.printf("[NTP] Heure actuelle : %04d-%02d-%02d %02d:%02d:%02d\n",
                  timeinfo.tm_year + 1900, timeinfo.tm_mon + 1, timeinfo.tm_mday,
                  timeinfo.tm_hour, timeinfo.tm_min, timeinfo.tm_sec);
  }

  // Mode non sécurisé (skip vérification du certificat serveur).
  // Pour la production : décommenter les lignes
  // espClient.setCACert(ca_crt);
  // espClient.setCertificate(client_crt);
  // espClient.setPrivateKey(client_key);
  // espClient.setHandshakeTimeout(8);
  espClient.setInsecure();

  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  mqttClient.setKeepAlive(60);
  mqttClient.setBufferSize(1024);

  tryConnectMQTT();
  delay(500);

  setupESPNow();

  Serial.printf("[INFO] MAC Gateway : %s\n", WiFi.macAddress().c_str());
  Serial.println("=== SYSTEME PRET ===");
}

/**
 * @brief Boucle principale : pompe MQTT, publie les snapshots en attente,
 *        surveille le canal et émet un heartbeat périodique.
 *
 * Stratégie de publication :
 *  - On regarde le prochain snapshot avec xQueuePeek (sans le retirer).
 *  - Si MQTT est connecté, on tente la publication ; on ne retire le message
 *    de la queue qu'en cas de succès pour garantir la livraison.
 *  - Si MQTT est down, le message reste en queue jusqu'à reconnexion.
 */
void loop() {
  if (mqttClient.connected()) {
    mqttClient.loop();
  } else {
    tryConnectMQTT();
  }
  SensorSnapshot snap;
  if (xQueuePeek(sensorQueue, &snap, 0) == pdTRUE) {
    if (mqttClient.connected()) {
      publishAll(snap);
      xQueueReceive(sensorQueue, &snap, 0);  
    }
  }

  if (millis() - lastChannelCheck > 30000UL) {
    lastChannelCheck = millis();
    checkChannel();
  }

  if (millis() - lastHeartbeat > 30000UL) {
    lastHeartbeat = millis();
    Serial.printf("[HEARTBEAT] MQTT:%s | Heap:%u octets | Canal:%d | Queue:%u msg\n",
                  mqttClient.connected() ? "OK" : "DOWN",
                  esp_get_free_heap_size(),
                  WiFi.channel(),
                  (unsigned)uxQueueMessagesWaiting(sensorQueue));
  }
}
