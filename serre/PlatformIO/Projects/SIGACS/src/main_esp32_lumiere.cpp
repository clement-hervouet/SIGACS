#include <Arduino.h>
#include <WiFi.h>
#include <PubSubClient.h>

#define LIGHT_PIN  39   // entrée analogique lumière

// ---- WiFi ----
const char* WIFI_SSID     = "TON_SSID";
const char* WIFI_PASSWORD = "TON_MDP";

// ---- MQTT ----
const char* MQTT_SERVER   = "";   // IP du broker
const int   MQTT_PORT     = 0000;    // Port du broker
// Topic pour ce bac : serre 1, bac 1, lumière
const char* MQTT_TOPIC    = "/serre/1/bac/1/lum";

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
    String clientId = "ESP32-Lum-";
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

  int   rawLight     = analogRead(LIGHT_PIN);
  float lightPercent = (rawLight / 4095.0f) * 100.0f;

  Serial.print("Luminosité : ");
  Serial.print(lightPercent);
  Serial.println(" %");

  String payload = String(lightPercent, 1);  // ex: "73.8"

  Serial.print("Publish MQTT: ");
  Serial.print(MQTT_TOPIC);
  Serial.print(" -> ");
  Serial.println(payload);

  mqttClient.publish(MQTT_TOPIC, payload.c_str());

  delay(1000);
}

