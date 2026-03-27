#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h> // Indispensable pour le TLS
#include <PubSubClient.h>
#include <M5StickC.h>
#include <time.h>

#define SOIL_PIN 32
#define SOIL_DRY 4095
#define SOIL_WET 0

const char* WIFI_SSID = "Serre";
const char* WIFI_PASSWORD = "stjolorient";

// MISE À JOUR DES IPs (basée sur ta capture pfSense)
const char* MQTT_SERVER = "192.168.42.66"; // VM Ubuntu Broker
const int MQTT_PORT = 8883;                // Port MQTT Sécurisé (mTLS)
const char* MQTT_TOPIC = "/serre/1/bac/1/sol";

const char* NTP_SERVER = "192.168.42.65";  // IP du pfSense (LAN_SERRE)

// ==============================================================
// CERTIFICATS mTLS (À remplacer par tes propres certificats)
// ==============================================================

// 1. Certificat de l'Autorité de Certification (CA)
const char* ca_cert = \
"-----BEGIN CERTIFICATE-----\n" \
"MIIDXTCCAkWgAwIBAgIJAL... (A REMPLACER PAR TON CA.CRT) ...\n" \
"-----END CERTIFICATE-----\n";

// 2. Certificat client (M5-Sol)
const char* client_cert = \
"-----BEGIN CERTIFICATE-----\n" \
"MIIDKzCCAhOgAwIBAgIUBM... (A REMPLACER PAR TON CLIENT.CRT) ...\n" \
"-----END CERTIFICATE-----\n";

// 3. Clé privée du client (M5-Sol)
const char* client_key = \
"-----BEGIN RSA PRIVATE KEY-----\n" \
"MIIEowIBAAKCAQEAw7i... (A REMPLACER PAR TON CLIENT.KEY) ...\n" \
"-----END RSA PRIVATE KEY-----\n";

// ==============================================================

WiFiClientSecure espClient; // Utilisation du client sécurisé au lieu de WiFiClient
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
  // On interroge uniquement le pfSense local (car pas d'accès Internet)
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
    Serial.println("NTP timeout : Verifier le service NTP sur pfSense");
  }
}

void setupTLS() {
  Serial.println("Configuration des certificats mTLS...");
  // On fournit les certificats au client sécurisé avant la connexion
  espClient.setCACert(ca_cert);
  espClient.setCertificate(client_cert);
  espClient.setPrivateKey(client_key);
}

void connectMQTT() {
  String clientId = "M5-Sol-";
  clientId += String((uint32_t)ESP.getEfuseMac(), HEX);
  clientId += "-sol"; // Pour éviter les conflits d'ID avec le M5-Air

  Serial.print("Connexion MQTT (Port 8883)");
  if (mqttClient.connect(clientId.c_str())) {
    Serial.println("\nMQTT OK (Chiffrement mTLS Actif)");
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
  
  analogSetAttenuation(ADC_11db); // Pour lire jusqu'à 3.3V sur l'ESP32
  pinMode(SOIL_PIN, INPUT);

  connectWiFi();
  
  // L'heure exacte est vitale pour la validation cryptographique du TLS
  syncNTP();     
  
  // Chargement des certificats
  setupTLS();    

  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  connectMQTT();
}

void loop() {
  M5.update();

  if (!mqttClient.connected()) {
    connectMQTT();
  }
  mqttClient.loop(); // Gère le PINGREQ automatique

  // Lecture du capteur d'humidité du sol
  int raw = analogRead(SOIL_PIN);
  Serial.printf("RAW : %d\n", raw);
  
  int humidity = map(raw, SOIL_WET, SOIL_DRY, 0, 100);
  humidity = constrain(humidity, 0, 100);

  Serial.printf("Humidité Sol : %d %%\n", humidity);
  
  // Publication sécurisée vers le broker
  mqttClient.publish(MQTT_TOPIC, String(humidity).c_str());

  delay(10000);
}