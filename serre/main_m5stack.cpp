#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <PubSubClient.h>
#include <freertos/queue.h>
#include "secrets.h"

// ─── CONFIG ──────────────────────────────────────────────────────────────────
#define SERRE_ID        1
#define BAC_ID          1
#define ESPNOW_CHANNEL  1

// ─── STRUCTURE PAQUET ESP-NOW ────────────────────────────────────────────────
typedef struct __attribute__((packed)) {
    char  type[4];        // "air\0" ou "sol\0"
    float temperature;
    float humidity;
} SensorData;

// ─── SNAPSHOT PUBLIÉ VIA MQTT ────────────────────────────────────────────────
// Contient les dernières valeurs agrégées au moment de l'envoi.
typedef struct {
    float temperatureAmbiante;
    float humiditeAmbiante;
    float humiditeSol;
} SensorSnapshot;

// ─── OBJETS GLOBAUX ──────────────────────────────────────────────────────────
WiFiClientSecure espClient;
PubSubClient     mqttClient(espClient);

// Queue FreeRTOS : le callback ESP-NOW (tâche wifi) produit,
// loop() consomme — thread-safe sans mutex.
QueueHandle_t sensorQueue;

// Valeurs courantes — sentinelle -999 = "pas encore reçu"
volatile float lastTempAmbiante = -999.0f;
volatile float lastHumAmbiante  = -999.0f;
volatile float lastHumSol       = -999.0f;

// Timers
unsigned long lastMqttRetry    = 0;
unsigned long lastHeartbeat    = 0;
unsigned long lastChannelCheck = 0;
unsigned long mqttBackoff      = 5000UL;   // backoff exponentiel (5 s → 60 s max)

// Client ID stable (calculé une seule fois dans setup)
String clientId;

// ─── MQTT : PUBLICATION ──────────────────────────────────────────────────────

void publishAll(const SensorSnapshot& snap) {
    // Refuse de publier si l'une des trois valeurs n'a jamais été reçue
    if (snap.temperatureAmbiante < -100.0f ||
        snap.humiditeAmbiante    < -100.0f ||
        snap.humiditeSol         < -100.0f) {
        Serial.println("[MQTT] En attente : valeurs incompletes (air + sol requis)");
        return;
    }

    char topic[48];
    char payload[128];
    snprintf(topic,   sizeof(topic),   "serre/%d/bac/%d", SERRE_ID, BAC_ID);
    snprintf(payload, sizeof(payload),
             "{\"temperatureAmbiante\":%.1f,\"humiditeAmbiante\":%.1f,\"humiditeSol\":%.1f}",
             snap.temperatureAmbiante, snap.humiditeAmbiante, snap.humiditeSol);

    bool ok = mqttClient.publish(topic, payload);
    Serial.printf("[MQTT] %s -> %s [%s]\n", topic, payload, ok ? "PUB OK" : "FAIL");
}

// ─── MQTT : CONNEXION (appelée depuis loop, non-bloquante) ───────────────────

void tryConnectMQTT() {
    if (millis() - lastMqttRetry < mqttBackoff) return;
    lastMqttRetry = millis();

    Serial.printf("[MQTT] Connexion a %s:%d (backoff %lus)...\n",
                  MQTT_SERVER, MQTT_PORT, mqttBackoff / 1000);

    // TLS strict : on utilise le CA cert sans désactiver la vérification
    espClient.setCACert(ca_crt);
    espClient.setCertificate(client_crt);
    espClient.setPrivateKey(client_key);
    espClient.setInsecure();
    espClient.setTimeout(5);   // Évite le freeze si handshake TLS lent

    if (mqttClient.connect(clientId.c_str())) {
        Serial.println("[MQTT] Connecte !");
        mqttBackoff = 5000UL;  // Reset du backoff après succès
    } else {
        Serial.printf("[MQTT] Echec rc=%d, prochain essai dans %lus\n",
                      mqttClient.state(), mqttBackoff / 1000);
        mqttBackoff = min(mqttBackoff * 2, 60000UL);  // Max 60 s
    }
}

// ─── CANAL : VÉRIFICATION PÉRIODIQUE ────────────────────────────────────────

void checkChannel() {
    uint8_t           primaryChan;
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

// ─── CALLBACK ESP-NOW ────────────────────────────────────────────────────────
//   Signature OBLIGATOIRE pour ESP32 Arduino Core v3.x
// L'ancienne signature (const uint8_t* mac_addr, ...) compile sans erreur
// mais le callback n'est JAMAIS appelé à l'exécution.

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

// ─── INIT WIFI ───────────────────────────────────────────────────────────────

void connectWiFi() {
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    Serial.print("[WiFi] Connexion");
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
        Serial.print(".");
    }
    WiFi.setSleep(false);  // Évite la coupure de l'antenne entre les paquets ESP-NOW
    Serial.printf("\n[WiFi] OK | IP: %s | Canal: %d\n",
                  WiFi.localIP().toString().c_str(), WiFi.channel());
}

// ─── INIT NTP ────────────────────────────────────────────────────────────────

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
    if (retries < 40)
        Serial.println("[NTP] OK");
    else
        Serial.println("[NTP] TIMEOUT — le handshake TLS risque d'echouer !");
}

// ─── INIT ESP-NOW ────────────────────────────────────────────────────────────

void setupESPNow() {
    // Force le même canal que le routeur pour la réception
    esp_wifi_set_promiscuous(true);
    esp_wifi_set_channel(ESPNOW_CHANNEL, WIFI_SECOND_CHAN_NONE);
    esp_wifi_set_promiscuous(false);

    if (esp_now_init() != ESP_OK) {
        Serial.println("[ESP-NOW] Erreur init !");
        return;
    }

    // Enregistrement avec la nouvelle signature Core v3.x
    esp_now_register_recv_cb(onDataRecv);
    Serial.println("[ESP-NOW] Gateway prete, en ecoute...");
}

// ─── SETUP ───────────────────────────────────────────────────────────────────

void setup() {
    Serial.begin(115200);

    sensorQueue = xQueueCreate(10, sizeof(SensorSnapshot));

    // Client ID calculé une seule fois (stable entre les reconnexions)
    clientId = "M5-Gateway-" + String((uint32_t)ESP.getEfuseMac(), HEX);

    connectWiFi();
    syncNTP();

    // 1. MQTT d'abord (handshake TLS lourd, bloquant une seule fois)
    mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
    mqttClient.setKeepAlive(60);
    mqttClient.setBufferSize(1024);
    tryConnectMQTT();

    delay(500);

    // 2. Ensuite ESP-NOW (écoute silencieuse)
    setupESPNow();

    Serial.printf("[INFO] MAC Gateway : %s\n", WiFi.macAddress().c_str());
    Serial.println("=== SYSTEME PRET ===");
}

// ─── LOOP ────────────────────────────────────────────────────────────────────

void loop() {
    // 1. Maintien MQTT
    if (mqttClient.connected()) {
        mqttClient.loop();
    } else {
        tryConnectMQTT();  // Reconnexion non-bloquante avec backoff
    }

    // 2. Traitement des mesures reçues via ESP-NOW
    SensorSnapshot snap;
    while (xQueueReceive(sensorQueue, &snap, 0) == pdTRUE) {
        if (mqttClient.connected()) {
            publishAll(snap);
        } else {
            // MQTT indisponible : on remet en queue si de la place,
            // sinon la mesure la plus ancienne est déjà écrasée (queue FIFO).
            if (xQueueSend(sensorQueue, &snap, 0) != pdTRUE) {
                Serial.println("[WARN] MQTT down + queue pleine : mesure perdue");
            }
            break;  // Inutile de boucler si MQTT est down
        }
    }

    // 3. Vérification du canal ESP-NOW toutes les 30 s
    if (millis() - lastChannelCheck > 30000UL) {
        lastChannelCheck = millis();
        checkChannel();
    }

    // 4. Heartbeat toutes les 30 s avec métriques utiles
    if (millis() - lastHeartbeat > 30000UL) {
        lastHeartbeat = millis();
        Serial.printf("[HEARTBEAT] MQTT:%s | Heap:%u octets | Canal:%d | Queue:%u msg\n",
                      mqttClient.connected() ? "OK" : "DOWN",
                      esp_get_free_heap_size(),
                      WiFi.channel(),
                      (unsigned)uxQueueMessagesWaiting(sensorQueue));
    }
}