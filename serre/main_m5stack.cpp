#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <PubSubClient.h>
#include <freertos/queue.h>
#include "secrets.h"

#define SERRE_ID        1
#define BAC_ID          1
#define ESPNOW_CHANNEL  1

typedef struct __attribute__((packed)) {
  char  type[4]; // "air\0" ou "sol\0"
  float temperature;
  float humidity;
} SensorData;

typedef struct {
  float temperatureAmbiante;
  float humiditeAmbiante;
  float humiditeSol;
} SensorSnapshot;

WiFiClientSecure espClient;
PubSubClient     mqttClient(espClient);
QueueHandle_t    sensorQueue;

volatile float lastTempAmbiante = -999.0f;
volatile float lastHumAmbiante  = -999.0f;
volatile float lastHumSol       = -999.0f;

unsigned long lastMqttRetry    = 0;
unsigned long lastHeartbeat    = 0;
unsigned long lastChannelCheck = 0;
unsigned long mqttBackoff      = 5000UL;

String clientId;

void syncNTP();
void connectWiFi();
void setupESPNow();
void checkChannel();
void publishAll(const SensorSnapshot& snap);
void tryConnectMQTT();

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

void checkChannel() {
  uint8_t primaryChan;
  wifi_second_chan_t secondChan;
  esp_wifi_get_channel(&primaryChan, &secondChan);
  if (primaryChan != ESPNOW_CHANNEL) {
    Serial.printf("[WARN] Canal derive sur %d, correction sur %d...\n",
                  primaryChan, ESPNOW_CHANNEL);
    esp_wifi_set_promiscuous(true);
    esp_wifi_set_channel(ESPNOW_CHANNEL, WIFI_SECOND_CHAN_NONE);
    esp_wifi_set_promiscuous(false);
  }
}

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

void syncNTP() {
  configTime(0, 0, NTP_SERVER);
  // TZ Europe/Paris (CET/CEST).
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

  // espClient.setCACert(ca_crt);
  // espClient.setCertificate(client_crt);
  // espClient.setPrivateKey(client_key);
  // espClient.setHandshakeTimeout(8);

  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  mqttClient.setKeepAlive(60);
  mqttClient.setBufferSize(1024);

  tryConnectMQTT();
  delay(500);

  setupESPNow();

  Serial.printf("[INFO] MAC Gateway : %s\n", WiFi.macAddress().c_str());
  Serial.println("=== SYSTEME PRET ===");
}

void loop() {
  if (mqttClient.connected()) {
    mqttClient.loop();
  } else {
    tryConnectMQTT();
  }
  SensorSnapshot snap;
  if (xQueuePeek(sensorQueue, &snap, 0) == pdTRUE) {
    if (mqttClient.connected()) {
      // On consomme seulement si publish réussit ; sinon on laisse en queue
      publishAll(snap);
      xQueueReceive(sensorQueue, &snap, 0);
    }
    // Si MQTT down : on ne consomme rien, la mesure reste pour plus tard
  }

  if (millis() - lastChannelCheck > 30000UL) {
    lastChannelCheck = millis();
    checkChannel();
  }

  // 4. Heartbeat toutes les 30 s
  if (millis() - lastHeartbeat > 30000UL) {
    lastHeartbeat = millis();
    Serial.printf("[HEARTBEAT] MQTT:%s | Heap:%u octets | Canal:%d | Queue:%u msg\n",
                  mqttClient.connected() ? "OK" : "DOWN",
                  esp_get_free_heap_size(),
                  WiFi.channel(),
                  (unsigned)uxQueueMessagesWaiting(sensorQueue));
  }
}
