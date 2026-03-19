#include <Arduino.h>
#include <WiFi.h>
#include <PubSubClient.h>
#include <M5StickC.h>
#include <time.h>

#define SOIL_PIN  32
#define SOIL_DRY  4095
#define SOIL_WET  0

const char* WIFI_SSID     = "Serre";
const char* WIFI_PASSWORD = "stjolorient";
const char* MQTT_SERVER   = "192.168.42.65";
const int   MQTT_PORT     = 1883;
const char* MQTT_TOPIC    = "/serre/1/bac/1/sol";

WiFiClient   espClient;
PubSubClient mqttClient(espClient);

void connectWiFi() {
  Serial.print("Connexion WiFi");
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println();
  Serial.println("WiFi OK");
  Serial.print("IP : ");
  Serial.println(WiFi.localIP());
}

void syncNTP() {
  configTime(0, 0, "pool.ntp.org", "192.168.42.65");
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
    Serial.printf("NTP : %02d:%02d %02d/%02d/%04d\n",
      timeinfo.tm_hour, timeinfo.tm_min,
      timeinfo.tm_mday, timeinfo.tm_mon + 1, timeinfo.tm_year + 1900);
  } else {
    Serial.println("NTP timeout");
  }
}

void connectMQTT() {
  String clientId = "M5-Sol-";
  clientId += String((uint32_t)ESP.getEfuseMac(), HEX);
  clientId += "-sol";  // ← unique, évite conflit avec M5-Air

  mqttClient.connect(clientId.c_str());
}

void setup() {
  Serial.begin(115200);
  delay(1000);
  M5.begin();
  M5.Lcd.setRotation(3);
  M5.Lcd.fillScreen(BLACK);
  M5.Axp.SetLDO2(false);
  analogSetAttenuation(ADC_11db);
  pinMode(SOIL_PIN, INPUT);

  connectWiFi();
  syncNTP();

  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  connectMQTT();
}

void loop() {
  M5.update();

  if (!mqttClient.connected()) connectMQTT();
  mqttClient.loop();

  int raw = analogRead(SOIL_PIN);
  Serial.printf("RAW : %d\n", raw);
  int humidity = map(raw, SOIL_WET, SOIL_DRY, 0, 100);
  humidity = constrain(humidity, 0, 100);

  Serial.printf("Humidité Sol : %d %%\n", humidity);
  mqttClient.publish(MQTT_TOPIC, String(humidity).c_str());

  delay(5000);
}