#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <PubSubClient.h>
#include <M5Stack.h>
#include "secrets.h"

#define SERRE_ID 1
#define BAC_ID   1


WiFiClientSecure espClient;
PubSubClient mqttClient(espClient);
unsigned long lastMqttRetry = 0;

float lastHumiditeAmbiante   = -1;
float lastTemperatureAmbiante = -1;
float lastHumiditeSol        = -1;

// Structs ESP-NOW
typedef struct {
  char  type[4];      
  float temperature;
  float humidity;
} AirData;            

typedef struct {
  float humidite_sol;
} SoilData;           

void publishAll(char* topic) {
  char payload[128];

  // Publish individuel humiditeAmbiante
  if (lastHumiditeAmbiante >= 0) {
    snprintf(payload, sizeof(payload), "{\"humiditeAmbiante\":%.1f}", lastHumiditeAmbiante);
    mqttClient.publish(topic, payload);
    Serial.printf("MQTT → %s : %s\n", topic, payload);
  }

  // Publish individuel temperatureAmbiante
  if (lastTemperatureAmbiante >= 0) {
    snprintf(payload, sizeof(payload), "{\"temperatureAmbiante\":%.1f}", lastTemperatureAmbiante);
    mqttClient.publish(topic, payload);
    Serial.printf("MQTT → %s : %s\n", topic, payload);
  }

  // Publish individuel humiditeSol
  if (lastHumiditeSol >= 0) {
    snprintf(payload, sizeof(payload), "{\"humiditeSol\":%.1f}", lastHumiditeSol);
    mqttClient.publish(topic, payload);
    Serial.printf("MQTT → %s : %s\n", topic, payload);
  }

  // Publish combiné si toutes les valeurs sont disponibles
  if (lastHumiditeAmbiante >= 0 && lastTemperatureAmbiante >= 0 && lastHumiditeSol >= 0) {
    snprintf(payload, sizeof(payload),
      "{\"humiditeAmbiante\":%.1f,\"temperatureAmbiante\":%.1f,\"humiditeSol\":%.1f}",
      lastHumiditeAmbiante, lastTemperatureAmbiante, lastHumiditeSol);
    mqttClient.publish(topic, payload);
    Serial.printf("MQTT combiné → %s : %s\n", topic, payload);
  }
}

void onDataRecv(const uint8_t *mac_addr, const uint8_t *data, int len) {
  char topic[32];
  snprintf(topic, sizeof(topic), "/serre/%d/bac/%d", SERRE_ID, BAC_ID);

  M5.Lcd.fillScreen(BLACK);
  M5.Lcd.setCursor(0, 0);
  M5.Lcd.setTextSize(2);

  if (len == sizeof(AirData)) {
    AirData received;
    memcpy(&received, data, sizeof(received));
    lastTemperatureAmbiante = received.temperature;
    lastHumiditeAmbiante    = received.humidity;
    Serial.printf("ESP-NOW air → T:%.1f H:%.1f\n", received.temperature, received.humidity);
    M5.Lcd.printf("T:%.1f C\nH:%.1f %%\n", received.temperature, received.humidity);

  } else if (len == sizeof(SoilData)) {
    SoilData received;
    memcpy(&received, data, sizeof(received));
    lastHumiditeSol = received.humidite_sol;
    Serial.printf("ESP-NOW sol → Sol:%.1f\n", received.humidite_sol);
    M5.Lcd.printf("Sol:%.1f %%\n", received.humidite_sol);
  }

  if (mqttClient.connected()) {
    publishAll(topic);
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
    WiFi.localIP().toString().c_str(), WiFi.channel());
}

void syncNTP() {
  configTime(0, 0, "pool.ntp.org", "time.google.com");
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
    M5.Lcd.printf("MQTT ECHEC rc=%d\n", mqttClient.state());
  }
}

void setupESPNow() {
  if (esp_now_init() != ESP_OK) {
    Serial.println("Erreur init ESP-NOW");
    M5.Lcd.println("ESP-NOW ERREUR");
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

  espClient.setCACert(ca_cert);
  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  connectMQTT();

  Serial.printf("MAC Gateway : %s\n", WiFi.macAddress().c_str());
  M5.Lcd.printf("MAC:\n%s\n", WiFi.macAddress().c_str());
}

void loop() {
  M5.update();

  if (!mqttClient.connected() && millis() - lastMqttRetry > 10000) {
    lastMqttRetry = millis();
    connectMQTT();
  }

  mqttClient.loop();
}