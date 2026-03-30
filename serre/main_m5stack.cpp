#include <Arduino.h>
#include <WiFi.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <PubSubClient.h>
#include <M5Stack.h>
#include "secrets.h"

const char* MQTT_TOPIC_SOL = "/serre/1/bac/1/sol";

WiFiClient espClient;
PubSubClient mqttClient(espClient);

typedef struct {
  float humidite_sol;
} SoilData;

void onDataRecv(const uint8_t *mac_addr, const uint8_t *data, int len) {
  SoilData received;
  memcpy(&received, data, sizeof(received));

  Serial.printf("ESP-NOW reçu - Humidite Sol : %.0f %%\n", received.humidite_sol);

  M5.Lcd.fillScreen(BLACK);
  M5.Lcd.setCursor(0, 0);
  M5.Lcd.setTextSize(2);
  M5.Lcd.printf("Sol : %.0f %%\n", received.humidite_sol);

  if (mqttClient.connected()) {
    String payload = String((int)received.humidite_sol);
    mqttClient.publish(MQTT_TOPIC_SOL, payload.c_str());
    Serial.println("MQTT publié");
    M5.Lcd.println("MQTT OK");
  } else {
    Serial.println("MQTT non connecté");
    M5.Lcd.println("MQTT ECHEC");
  }
}

void connectWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connexion WiFi");
  M5.Lcd.println("WiFi...");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi OK");
  Serial.printf("IP : %s\n", WiFi.localIP().toString().c_str());
  Serial.printf("Canal : %d\n", WiFi.channel());
  M5.Lcd.printf("WiFi OK\nIP:%s\nCanal:%d\n",
    WiFi.localIP().toString().c_str(),
    WiFi.channel());
}

void syncNTP() {
  configTime(0, 0, "pool.ntp.org", MQTT_SERVER);
  setenv("TZ", "CET-1CEST,M3.5.0,M10.5.0/3", 1);
  tzset();

  struct tm timeinfo;
  int retries = 0;
  Serial.print("NTP");
  M5.Lcd.println("NTP...");
  while (!getLocalTime(&timeinfo) && retries < 40) {
    delay(500);
    Serial.print(".");
    retries++;
  }
  Serial.println();
  if (retries < 40) {
    Serial.printf("NTP : %02d:%02d %02d/%02d/%04d\n",
      timeinfo.tm_hour, timeinfo.tm_min,
      timeinfo.tm_mday, timeinfo.tm_mon + 1, timeinfo.tm_year + 1900);
  } else {
    Serial.println("NTP timeout");
  }
}

void connectMQTT() {
  String clientId = "M5-Gateway-";
  clientId += String((uint32_t)ESP.getEfuseMac(), HEX);
  if (mqttClient.connect(clientId.c_str())) {
    Serial.println("MQTT OK");
    M5.Lcd.println("MQTT OK");
  } else {
    Serial.printf("MQTT ECHEC, rc=%d\n", mqttClient.state());
  }
}

void setupESPNow() {
  if (esp_now_init() != ESP_OK) {
    Serial.println("Erreur init ESP-NOW");
    return;
  }
  esp_now_register_recv_cb(onDataRecv);
  Serial.println("ESP-NOW gateway prête");
  M5.Lcd.println("ESP-NOW OK");
}

void setup() {
  Serial.begin(115200);
  M5.begin(true, false, true);
  M5.Lcd.setRotation(3);
  M5.Lcd.fillScreen(BLACK);
  M5.Lcd.setTextColor(WHITE);
  M5.Lcd.setTextSize(2);
  M5.Lcd.setCursor(0, 0);

  connectWiFi();
  syncNTP();      
  setupESPNow();

  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  connectMQTT();

  Serial.printf("MAC Gateway : %s\n", WiFi.macAddress().c_str());
  M5.Lcd.printf("MAC:\n%s\n", WiFi.macAddress().c_str());
}

void loop() {
  M5.update();
  if (!mqttClient.connected()) connectMQTT();
  mqttClient.loop();
  delay(10);
}