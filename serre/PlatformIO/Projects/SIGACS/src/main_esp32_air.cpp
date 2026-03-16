#include <Arduino.h>
#include <WiFi.h>
#include <PubSubClient.h>
#include <M5StickC.h>
#include <Adafruit_Sensor.h>
#include <DHT.h>
#include "time.h"  

#define DHTPIN 33
#define DHTTYPE DHT22

const char* WIFI_SSID     = "Serre";
const char* WIFI_PASSWORD = "stjolorient";
const char* MQTT_SERVER   = "broker.hivemq.com";
const int MQTT_PORT       = 1883;
const char* MQTT_TOPIC    = "/serre/1/bac/1/air";

// NTP E2
const char* ntpServer = "0.pfsense.pool.ntp.org";

WiFiClient espClient;
PubSubClient mqttClient(espClient);
DHT dht(DHTPIN, DHTTYPE);

void connectWiFi() {
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connexion WiFi");
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nWiFi OK");
  Serial.print("IP : ");
  Serial.println(WiFi.localIP());
}

void syncNTP() {
  
  // Config avec routeur
  configTime(3600, 3600, "192.168.42.65");
  
  struct tm timeinfo;
  int tentatives = 0;
  
  // BOUCLE ATTENTE 10s max [web:276]
  while (!getLocalTime(&timeinfo) && tentatives < 20) {
    delay(500);
    Serial.print(".");
    tentatives++;
  }
  
  if (getLocalTime(&timeinfo)) {
    Serial.printf("\n Heure: %02d:%02d %02d/%02d/%04d\n", 
      timeinfo.tm_hour, timeinfo.tm_min, 
      timeinfo.tm_mday, timeinfo.tm_mon+1, timeinfo.tm_year+1900);
  } else {
    Serial.println("\n Timeout NTP");
    Serial.println("→ Vérif pfSense:");
    Serial.println("  Services > NTP > Enable ON");
    Serial.println("  Interfaces > LAN ON");
    Serial.println("  Firewall > LAN > UDP/123 autorisé");
  }
}



void connectMQTT() {}

void setup() {
  M5.begin();
  Serial.begin(115200);
  
  pinMode(DHTPIN, INPUT_PULLUP);
  dht.begin();
  
  connectWiFi();
  syncNTP();
  
  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  connectMQTT();
}

void loop() {
  M5.update();
  if (!mqttClient.connected()) connectMQTT();
  mqttClient.loop();

  float humAir  = dht.readHumidity();
  float tempAir = dht.readTemperature();

  if (!isnan(humAir) && !isnan(tempAir)) {
    Serial.printf("T:%.1f°C H:%.1f%%\n", tempAir, humAir);
    String payload = String(tempAir, 1) + ";" + String(humAir, 1);
    mqttClient.publish(MQTT_TOPIC, payload.c_str());
  } else {
    Serial.println("Erreur DHT22"); // Erreur de lecture du capteur
  }

  delay(5000);  // 5min ici mais 15min dans la version finale pour éviter de spammer le broker et économiser la batterie
}
