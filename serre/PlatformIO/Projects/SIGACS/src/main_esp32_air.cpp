#include <Arduino.h>
#include <WiFi.h>
#include <PubSubClient.h>
#include "DHT12.h"

// ---- WiFi ----
const char* WIFI_SSID     = "iPhone";
const char* WIFI_PASSWORD = "alan2006";

// ---- MQTT ----
const char* MQTT_SERVER   = "broker.hivemq.com";   // IP du broker en ligne
const int   MQTT_PORT     = 1883;    // Port du broker

// Topic pour ce bac : serre 1, bac 1, air
const char* MQTT_TOPIC    = "/serre/1/bac/1/air";

WiFiClient espClient;
PubSubClient mqttClient(espClient);
DHT12 dht12;  // I2C DHT12 pour ENV HAT v1.2

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
    String clientId = "M5StickC-Air-";
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
  M5.begin();      // init M5StickC + écran
  Serial.begin(115200);
  Wire.begin();    // I2C pour Grove/ENV HAT
  
  dht12.begin(0x5C);  // adresse I2C DHT12
  
  connectWiFi();
  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  connectMQTT();
}

void loop() {
  M5.update();  // bouton/écran M5
  
  if (!mqttClient.connected()) {
    connectMQTT();
  }
  mqttClient.loop();

  // Lecture DHT12
  float humAir  = dht12.readHumidity();
  float tempAir = dht12.readTemperature();

  Serial.print("Température Air : ");
  Serial.print(tempAir);
  Serial.println(" °C");

  Serial.print("Humidité Air : ");
  Serial.print(humAir);
  Serial.println(" %");

  // Vérif valeurs valides
  if (isnan(humAir) || isnan(tempAir)) {
    Serial.println("Erreur DHT12 ! Skip MQTT");
  } else {
    // format "temp;hum" ex: "23.5;45.2"
    String payload = String(tempAir, 1) + ";" + String(humAir, 1);

    Serial.print("Publish MQTT: ");
    Serial.print(MQTT_TOPIC);
    Serial.print(" -> ");
    Serial.println(payload);

    mqttClient.publish(MQTT_TOPIC, payload.c_str());
  }

  delay(900000);  // 15 min
}
