#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h> 
#include <PubSubClient.h>
#include <M5StickC.h>
#include <Adafruit_Sensor.h>
#include <DHT.h>
#include <time.h>

#define DHTPIN 33
#define DHTTYPE DHT22

const char* WIFI_SSID = "Serre";
const char* WIFI_PASSWORD = "stjolorient";
const char* MQTT_SERVER = "192.168.42.66";
const int MQTT_PORT = 8883; 
const char* MQTT_TOPIC = "/serre/1/bac/1/air";

const char* NTP_SERVER = "192.168.42.65"; 

// ==============================================================
// CERTIFICATS mTLS (À remplacer par tes propres certificats générés)
// ==============================================================

// Certificat de l'Autorité de Certification (CA) pour vérifier le Broker
const char* ca_cert = \
"-----BEGIN CERTIFICATE-----\n" \
"MIIDXTCCAkWgAwIBAgIJAL... (A COMPLETER) ...\n" \
"-----END CERTIFICATE-----\n";

const char* client_cert = \
"-----BEGIN CERTIFICATE-----\n" \
"MIIDKzCCAhOgAwIBAgIUBM... (A COMPLETER) ...\n" \
"-----END CERTIFICATE-----\n";

const char* client_key = \
"-----BEGIN RSA PRIVATE KEY-----\n" \
"MIIEowIBAAKCAQEAw7i... (A COMPLETER) ...\n" \
"-----END RSA PRIVATE KEY-----\n";



WiFiClientSecure espClient; 
PubSubClient mqttClient(espClient);
DHT dht(DHTPIN, DHTTYPE);

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
  configTime(0, 0, NTP_SERVER);
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

void setupTLS() {
  Serial.println("Configuration mTLS en cours...");
  espClient.setCACert(ca_cert);
  espClient.setCertificate(client_cert);
  espClient.setPrivateKey(client_key);
}

void connectMQTT() {
  String clientId = "M5-Air-";
  clientId += String((uint32_t)ESP.getEfuseMac(), HEX);

  Serial.print("Connexion MQTT (Sécurisée)");
  if (mqttClient.connect(clientId.c_str())) {
    Serial.println("\nMQTT OK");
  } else {
    Serial.printf(" Erreur état : %d\n", mqttClient.state());
  }
}

void setup() {
  Serial.begin(115200);
  delay(1000);
  M5.begin();
  M5.Lcd.setRotation(3);
  M5.Lcd.fillScreen(BLACK);
  M5.Axp.SetLDO2(false);
  dht.begin();

  connectWiFi();
  syncNTP();     
  setupTLS();    

  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  connectMQTT();
}

void loop() {
  M5.update();

  if (!mqttClient.connected()) {
    connectMQTT();
  }
  mqttClient.loop(); 

  float humAir = dht.readHumidity();
  float tempAir = dht.readTemperature();

  if (!isnan(humAir) && !isnan(tempAir)) {
    Serial.printf("T: %.1f°C H: %.1f%%\n", tempAir, humAir);

    char payload[64];
    snprintf(payload, sizeof(payload),
             "{\"temperature\":%.1f,\"humidity\":%.1f}",
             tempAir, humAir);

    Serial.printf("Publish MQTT: %s -> %s\n", MQTT_TOPIC, payload);
    mqttClient.publish(MQTT_TOPIC, payload);
  } else {
    Serial.println("Erreur lecture DHT22");
  }

  delay(10000);
}