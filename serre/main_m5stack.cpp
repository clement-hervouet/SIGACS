#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <PubSubClient.h>
#include "secrets.h"

// ─── CONFIG ──────────────────────────────────────────────────────────────────
#define SERRE_ID 1
#define BAC_ID   1

// ─── OBJETS GLOBAUX ──────────────────────────────────────────────────────────
WiFiClientSecure espClient;
PubSubClient     mqttClient(espClient);
unsigned long    lastMqttRetry = 0;
unsigned long    lastHeartbeat = 0; // Pour vérifier que la boucle tourne

float lastHumiditeAmbiante    = -1;
float lastTemperatureAmbiante = -1;
float lastHumiditeSol         = -1;

volatile bool pendingPublish = false;

typedef struct __attribute__((packed)) {
  char  type[4];       
  float temperature;
  float humidity;
} SensorData;          

// ─── FONCTIONS MQTT ──────────────────────────────────────────────────────────

void publishAll() {
  char topic[48];
  char payload[128];
  snprintf(topic, sizeof(topic), "serre/%d/bac/%d", SERRE_ID, BAC_ID);

  if (lastTemperatureAmbiante >= 0 && lastHumiditeAmbiante >= 0 && lastHumiditeSol >= 0) {
    snprintf(payload, sizeof(payload),
      "{\"temperatureAmbiante\":%.1f,\"humiditeAmbiante\":%.1f,\"humiditeSol\":%.1f}",
      lastTemperatureAmbiante, lastHumiditeAmbiante, lastHumiditeSol);
      
    bool ok = mqttClient.publish(topic, payload);
    
    Serial.printf("MQTT -> %s : %s [%s]\n", topic, payload, ok ? "PUB OK" : "FAIL");
    // lastHumiditeSol = -1.0; 
  } else {
    Serial.println("MQTT en attente : valeurs incompletes (attente air + sol)");
  }
}

void connectMQTT() {
  Serial.printf("[MQTT] Connexion a %s:%d...\n", MQTT_SERVER, MQTT_PORT);
  
  espClient.setCACert(ca_crt);
  espClient.setCertificate(client_crt);
  espClient.setPrivateKey(client_key);
  
  // Désactive la vérification stricte du certificat CA (utile pour le debug)
  espClient.setInsecure();
  espClient.setTimeout(5); // Évite le freeze total si le handshake TLS est lent
  
  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  mqttClient.setKeepAlive(60);     
  mqttClient.setBufferSize(1024);  // Buffer augmenté à 1024 pour éviter les crash TLS

  String clientId = "M5-Gateway-";
  clientId += String((uint32_t)ESP.getEfuseMac(), HEX);

  if (mqttClient.connect(clientId.c_str())) {
    Serial.println("[MQTT] OK Connecte !");
  } else {
    Serial.printf("[MQTT] ECHEC, rc=%d\n", mqttClient.state());
  }
}

// ─── CALLBACK ESP-NOW ────────────────────────────────────────────────────────

void onDataRecv(const uint8_t* mac_addr, const uint8_t* data, int len) {
  if (len != sizeof(SensorData)) {
    Serial.printf("ESP-NOW : taille inattendue %d octets\n", len);
    return;
  }

  SensorData received;
  memcpy(&received, data, sizeof(received));
  received.type[3] = '\0'; 

  if (strncmp(received.type, "air", 3) == 0) {
    lastTemperatureAmbiante = received.temperature;
    lastHumiditeAmbiante    = received.humidity;
    Serial.printf("ESP-NOW [air] -> T:%.1f°C  H:%.1f%%\n", received.temperature, received.humidity);
  } 
  else if (strncmp(received.type, "sol", 3) == 0) {
    lastHumiditeSol = received.humidity;
    Serial.printf("ESP-NOW [sol] -> Sol:%.1f%%\n", received.humidity);
  }

  pendingPublish = true; // Lève le drapeau pour la boucle loop()
}

// ─── INIT WIFI & NTP & ESP-NOW ───────────────────────────────────────────────

void connectWiFi() {
  WiFi.mode(WIFI_AP_STA); 
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connexion WiFi");
  
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  // Empêche l'antenne de s'éteindre et de rater les paquets ESP-NOW
  WiFi.setSleep(false); 
  
  Serial.printf("\nWiFi OK\nIP : %s\nCanal : %d\n", WiFi.localIP().toString().c_str(), WiFi.channel());
}

void syncNTP() {
  configTime(0, 0, NTP_SERVER);
  setenv("TZ", "CET-1CEST,M3.5.0,M10.5.0/3", 1);
  tzset();

  struct tm timeinfo;
  int retries = 0;
  Serial.print("NTP");
  while (!getLocalTime(&timeinfo) && retries < 40) {
    delay(500);
    Serial.print(".");
    retries++;
  }
  Serial.println();

  if (retries < 40) {
    Serial.printf("NTP OK\n");
  } else {
    Serial.println("NTP timeout - TLS va echouer");
  }
}

void setupESPNow() {
  // Forcer le canal ESP-NOW sur le même canal que le routeur WiFi
  esp_wifi_set_promiscuous(true);
  esp_wifi_set_channel(WiFi.channel(), WIFI_SECOND_CHAN_NONE);
  esp_wifi_set_promiscuous(false);

  if (esp_now_init() != ESP_OK) {
    Serial.println("Erreur init ESP-NOW");
    return;
  }
  esp_now_register_recv_cb(onDataRecv);
  Serial.println("ESP-NOW gateway prete");
}

// ─── SETUP ───────────────────────────────────────────────────────────────────

void setup() {
  Serial.begin(115200);
  
  connectWiFi();
  syncNTP();
  
  // 1. On connecte MQTT d'abord (TLS lourd)
  connectMQTT();
  delay(500); 
  
  // 2. Ensuite seulement, on active l'écoute silencieuse ESP-NOW
  setupESPNow();
  delay(100);

  Serial.printf("MAC Gateway : %s\n", WiFi.macAddress().c_str());
  Serial.println("=== SYSTEME PRET ===");
}

// ─── LOOP ────────────────────────────────────────────────────────────────────

void loop() {
  // Reconnexion MQTT si nécessaire
  if (!mqttClient.connected() && millis() - lastMqttRetry > 10000) {
    lastMqttRetry = millis();
    connectMQTT();
  }

  // Maintient la connexion MQTT et gère les trames entrantes/sortantes
  mqttClient.loop();

  // Si on a reçu une donnée ESP-NOW, on publie
  if (pendingPublish) {
    pendingPublish = false; 
    if (mqttClient.connected()) {
      publishAll();
    } else {
      Serial.println("MQTT non connecte, donnee capteur ignoree...");
    }
  }

  // Battement de cœur pour confirmer que la boucle n'est pas bloquée
  if (millis() - lastHeartbeat > 5000) {
    lastHeartbeat = millis();
    Serial.println("[INFO] Gateway active, en attente de donnees ESP-NOW...");
  }
}