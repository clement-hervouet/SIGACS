#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <M5Stack.h>
#include "secrets.h"

// ─── CONFIG ──────────────────────────────────────────────────────────────────
#define SERRE_ID 1
#define BAC_ID 1
#define PUBLISH_INTERVAL_MS 20000  // Publication toutes les 20s
#define MQTT_RETRY_INTERVAL 10000 // Tentative reconnexion toutes les 10s

// ─── OBJETS GLOBAUX ──────────────────────────────────────────────────────────
WiFiClientSecure espClient;
PubSubClient mqttClient(espClient);

unsigned long lastPublish = 0;
unsigned long lastMqttRetry = 0;
uint32_t publishCount = 0;

// ─── WIFI ────────────────────────────────────────────────────────────────────
void connectWiFi()
{
  Serial.printf("\n[WiFi] Connexion a \"%s\"...\n", WIFI_SSID);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  uint8_t attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 40)
  {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED)
  {
    Serial.printf("\n[WiFi] OK\n");
    Serial.printf("[WiFi] IP     : %s\n", WiFi.localIP().toString().c_str());
    Serial.printf("[WiFi] Canal  : %d\n", WiFi.channel());
    Serial.printf("[WiFi] RSSI   : %d dBm\n", WiFi.RSSI());
    delay(1000);
  }
  else
  {
    Serial.println("\n[WiFi] ECHEC - reboot dans 5s");
    delay(5000);
    ESP.restart();
  }
}

// ─── NTP ─────────────────────────────────────────────────────────────────────
void syncNTP()
{
  Serial.print("[NTP] Synchronisation");
  configTime(0, 0, NTP_SERVER);
  setenv("TZ", "CET-1CEST,M3.5.0,M10.5.0/3", 1);
  tzset();

  struct tm timeinfo;
  int retries = 0;
  while (!getLocalTime(&timeinfo) && retries < 40)
  {
    delay(500);
    Serial.print(".");
    retries++;
  }
  Serial.println();

  if (retries < 40)
  {
    char buf[32];
    strftime(buf, sizeof(buf), "%d/%m/%Y %H:%M:%S", &timeinfo);
    Serial.printf("[NTP] OK : %s\n", buf);
    delay(1000);
  }
  else
  {
    Serial.println("[NTP] TIMEOUT - TLS risque d'echouer");
    delay(2000);
  }
}

void connectMQTT()
{
  Serial.printf("[MQTT] Connexion a %s:%d...\n", MQTT_SERVER, MQTT_PORT);
  espClient.setCACert(ca_crt);
  espClient.setCertificate(client_crt);
  espClient.setPrivateKey(client_key);
  // Pour bypasser les certs en debug seulement :
  espClient.setInsecure();

  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  mqttClient.setKeepAlive(30);

  String clientId = "M5-Test-";
  clientId += String((uint32_t)ESP.getEfuseMac(), HEX);
  Serial.printf("[MQTT] Client ID : %s\n", clientId.c_str());

  if (mqttClient.connect(clientId.c_str()))
  {
    Serial.println("[MQTT] OK Connecté !");
    delay(1000);
  }
  else
  {
    int rc = mqttClient.state();
    Serial.printf("[MQTT] ECHEC rc=%d\n", rc);
    Serial.printf("[MQTT] Heap libre : %d octets\n", esp_get_free_heap_size());
    char buf[24];
    snprintf(buf, sizeof(buf), "rc=%d", rc);
  }
}

// ─── PUBLICATION ─────────────────────────────────────────────────────────────
void publishTestMessage()
{
  char topic[48];
  char payload[128];
  snprintf(topic, sizeof(topic), "serre/%d/bac/%d", SERRE_ID, BAC_ID);

  float fakeTemp = 20.0f + (publishCount % 10);
  float fakeHum = 50.0f + (publishCount % 20);
  float fakeSol = 40.0f + (publishCount % 15);

  snprintf(payload, sizeof(payload),
           "{\"temperatureAmbiante\":%.1f,\"humiditeAmbiante\":%.1f,\"humiditeSol\":%.1f,\"testCount\":%lu}",
           fakeTemp, fakeHum, fakeSol, publishCount);

  bool ok = mqttClient.publish(topic, payload);
  publishCount++;

  Serial.printf("[MQTT] %s  ->  %s\n", ok ? "PUB OK" : "PUB FAIL", topic);
  Serial.printf("       Payload : %s\n", payload);
  Serial.printf("       Heap : %d octets  |  Uptime : %lus\n",
                esp_get_free_heap_size(), millis() / 1000);
}

// ─── SETUP ───────────────────────────────────────────────────────────────────
void setup()
{
  Serial.begin(115200);
  delay(500);

  Serial.println("\n=== TEST MQTT STANDALONE ===");

  connectWiFi();
  syncNTP();
  connectMQTT();

  Serial.printf("[INFO] MAC       : %s\n", WiFi.macAddress().c_str());
  Serial.printf("[INFO] Canal WiFi: %d\n", WiFi.channel());
  Serial.printf("[INFO] Heap libre: %d octets\n", esp_get_free_heap_size());
  Serial.println("[INFO] Boucle démarrée - publication toutes les 5s\n");
}

// ─── LOOP ────────────────────────────────────────────────────────────────────
void loop()
{
  M5.update();

  if (!mqttClient.connected())
  {
    if (millis() - lastMqttRetry > MQTT_RETRY_INTERVAL)
    {
      lastMqttRetry = millis();
      Serial.println("[MQTT] Déconnecté - reconnexion...");
      connectMQTT();
    }
    return;
  }

  mqttClient.loop();

  if (millis() - lastPublish > PUBLISH_INTERVAL_MS)
  {
    lastPublish = millis();
    publishTestMessage();
  }
}