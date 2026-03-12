#include <Arduino.h>
#include <WiFi.h>
#include <PubSubClient.h>
#include "DHT.h"

#define DHTPIN   32
#define DHTTYPE  DHT22

// ---- WiFi ----
const char* WIFI_SSID     = "TON_SSID";
const char* WIFI_PASSWORD = "TON_MDP";

// ---- MQTT ----
const char* MQTT_SERVER   = "192.168.1.100";   // IP du broker
const int   MQTT_PORT     = 1883;    // Port du broker
// Topic pour ce bac : serre 1, bac 1, air
const char* MQTT_TOPIC    = "/serre/1/bac/1/air";

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

void connectMQTT() {
  while (!mqttClient.connected()) {
    Serial.print("Connexion MQTT...");
    String clientId = "ESP32-Air-";
    clientId += String((uint32_t)ESP.getEfuseMac(), HEX);
    if (mqttClient.connect(clientId.c_str())) {
      Serial.println(" OK");
    } else {
      Serial.print(" échec, code=");
      Serial.print(mqttClient.state());
      Serial.println(" -> nouvel essai dans 2s");
      delay(2000);
    }
  }
}

void setup() {
  Serial.begin(115200);
  delay(1000);

  dht.begin();

  connectWiFi();
  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  connectMQTT();
}

void loop() {
  if (!mqttClient.connected()) {
    connectMQTT();
  }
  mqttClient.loop();

  float humAir  = dht.readHumidity();
  float tempAir = dht.readTemperature();

  if (isnan(humAir) || isnan(tempAir)) {
    Serial.println("Erreur DHT22");
    delay(2000);
    return;
  }

  Serial.print("Température Air : ");
  Serial.print(tempAir);
  Serial.println(" °C");

  Serial.print("Humidité Air : ");
  Serial.print(humAir);
  Serial.println(" %");

  // format simple "temp;hum" ex: "23.5;45.2"
  String payload = String(tempAir, 1) + ";" + String(humAir, 1);

  Serial.print("Publish MQTT: ");
  Serial.print(MQTT_TOPIC);
  Serial.print(" -> ");
  Serial.println(payload);

  mqttClient.publish(MQTT_TOPIC, payload.c_str());

  delay(2000);
}

