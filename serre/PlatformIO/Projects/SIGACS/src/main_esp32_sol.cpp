#include <Arduino.h>
#include <WiFi.h>
#include <PubSubClient.h>
#include <M5StickC.h>
#include <time.h>  

#define SOIL_PIN   36   

const char* WIFI_SSID     = "Serre";
const char* WIFI_PASSWORD = "stjolorient";
const char* MQTT_SERVER   = "192.168.42.65";  // local
const int MQTT_PORT       = 1883;
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

void syncNTP() {  
  Serial.println("NTP...");
  configTime(3600, 3600, "192.168.42.65");  // France + routeur
  
  struct tm timeinfo;
  delay(2000); 
  if (getLocalTime(&timeinfo)) {
    Serial.printf("Heure: %02d:%02d\n", timeinfo.tm_hour, timeinfo.tm_min);
  } else {
    Serial.println("NTP timeout");
  }
}

void connectMQTT() {
  String clientId = "M5-Sol-";
  clientId += String((uint32_t)ESP.getEfuseMac(), HEX);
  
  Serial.print("MQTT...");
   (mqttClient.connect(clientId.c_str()));
}


void setup() {
  M5.begin();
  Serial.begin(115200);
  delay(1000);
  analogSetAttenuation(ADC_11db);

  connectWiFi();
  syncNTP();  
  
  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  connectMQTT();
}

void loop() {
  M5.update();
  if (!mqttClient.connected()) connectMQTT();
  mqttClient.loop();
  int raw = map(analogRead(SOIL_PIN), 0, 950, 0, 100);
  Serial.print("Humidité Sol : ");
  Serial.print(raw);
  Serial.println(" %");

  String payload = String(raw);

  Serial.print("Publish MQTT: ");
  Serial.print(MQTT_TOPIC);
  Serial.print(" -> ");
  Serial.println(payload);

  mqttClient.publish(MQTT_TOPIC, payload.c_str());

  delay(5000);  // 
}

