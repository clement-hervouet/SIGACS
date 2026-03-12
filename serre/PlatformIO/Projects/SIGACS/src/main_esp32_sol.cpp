#include <Arduino.h>
#include <WiFi.h>
#include <PubSubClient.h>

#define SOIL_PIN   36   // entrée analogique capteur sol

// ---- WiFi ----
const char* WIFI_SSID     = "TON_SSID";
const char* WIFI_PASSWORD = "TON_MDP";

// ---- MQTT ----
<<<<<<< HEAD
const char* MQTT_SERVER   = "192.168.1.100";   // IP du broker 
const int   MQTT_PORT     = 1883;             // Port du broker
=======
const char* MQTT_SERVER   = "";   // IP du broker 
const int   MQTT_PORT     = 0000;             // Port du broker
>>>>>>> f64b0601e3aad993bd5df3c32b2edf43cbb8bb8a
// Topic pour ce bac : serre 1, bac 1
const char* MQTT_TOPIC    = "/serre/1/bac/1/sol";

WiFiClient espClient;
PubSubClient mqttClient(espClient);

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
    String clientId = "ESP32-Sol-";
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

  connectWiFi();
  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  connectMQTT();
}

void loop() {
  if (!mqttClient.connected()) {
    connectMQTT();
  }
  mqttClient.loop();

  // Lecture capteur sol
  int   rawSoil     = analogRead(SOIL_PIN);
  float soilPercent = map(rawSoil, 300, 950, 0, 100);
  soilPercent       = constrain(soilPercent, 0, 100);

  Serial.print("Humidité Sol : ");
  Serial.print(soilPercent);
  Serial.println(" %");

  String payload = String(soilPercent, 1);  

  Serial.print("Publish MQTT: ");
  Serial.print(MQTT_TOPIC);
  Serial.print(" -> ");
  Serial.println(payload);

  mqttClient.publish(MQTT_TOPIC, payload.c_str());

  delay(1000);
}

