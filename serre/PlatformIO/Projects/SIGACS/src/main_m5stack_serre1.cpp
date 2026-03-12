main_m5stack_serre1.cpp
#include <Arduino.h>
#include <M5Unified.h>
#include <WiFi.h>
#include <PubSubClient.h>

// ---- WiFi ----
const char* WIFI_SSID     = "TON_SSID";
const char* WIFI_PASSWORD = "TON_MDP";

// ---- MQTT ----
<<<<<<< HEAD
const char* MQTT_SERVER   = "192.168.1.100";   // même IP que sur les ESP32
const int   MQTT_PORT     = 1883;    // Port du broker
=======
const char* MQTT_SERVER   = "";   // même IP que sur les ESP32
const int   MQTT_PORT     = 0000;    // Port du broker
>>>>>>> f64b0601e3aad993bd5df3c32b2edf43cbb8bb8a

// Topics utilisés par les 3 ESP32
const char* TOPIC_AIR  = "/serre/1/bac/1/air";
const char* TOPIC_SOL  = "/serre/1/bac/1/sol";
const char* TOPIC_LUM  = "/serre/1/bac/1/lum";

WiFiClient espClient;
PubSubClient mqttClient(espClient);

// Variables pour stocker les valeurs reçues
float tempAir = NAN;
float humAir  = NAN;
float soil    = NAN;
float lightV  = NAN;

void callback(char* topic, byte* payload, unsigned int length) {
  String t = String(topic);
  String msg;
  for (unsigned int i = 0; i < length; i++) {
    msg += (char)payload[i];
  }

  Serial.print("Message MQTT reçu [");
  Serial.print(t);
  Serial.print("] : ");
  Serial.println(msg);

  if (t == TOPIC_SOL) {
    soil = msg.toFloat();
  } else if (t == TOPIC_LUM) {
    lightV = msg.toFloat();
  } else if (t == TOPIC_AIR) {
    int sep = msg.indexOf(';');
    if (sep > 0) {
      String sTemp = msg.substring(0, sep);
      String sHum  = msg.substring(sep + 1);
      tempAir = sTemp.toFloat();
      humAir  = sHum.toFloat();
    }
  }
}

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
    String clientId = "M5-Concentrateur-";
    clientId += String((uint32_t)ESP.getEfuseMac(), HEX);

    if (mqttClient.connect(clientId.c_str())) {
      Serial.println(" OK");
      mqttClient.subscribe(TOPIC_AIR);
      mqttClient.subscribe(TOPIC_SOL);
      mqttClient.subscribe(TOPIC_LUM);
    } else {
      Serial.print(" échec, code=");
      Serial.print(mqttClient.state());
      Serial.println(" -> nouvel essai dans 2s");
      delay(2000);
    }
  }
}

void setup() {
  auto cfg = M5.config();
  M5.begin(cfg);
  Serial.begin(115200);

  M5.Lcd.setTextSize(2);
  M5.Lcd.fillScreen(BLACK);
  M5.Lcd.setCursor(0, 0);
  M5.Lcd.println("M5Stack SIGACS");
  M5.Lcd.println("Connexion...");

  connectWiFi();
  mqttClient.setServer(MQTT_SERVER, MQTT_PORT);
  mqttClient.setCallback(callback);
  connectMQTT();
}

void loop() {
  if (!mqttClient.connected()) {
    connectMQTT();
  }
  mqttClient.loop();

  M5.Lcd.fillScreen(BLACK);
  M5.Lcd.setCursor(0, 0);
  M5.Lcd.println("Serre 1 - Bac 1");

  M5.Lcd.println("=== Air ===");
  if (isnan(tempAir) || isnan(humAir)) {
    M5.Lcd.println("En attente...");
  } else {
    M5.Lcd.printf("T: %.1f C\n", tempAir);
    M5.Lcd.printf("H: %.1f %%\n", humAir);
  }

  M5.Lcd.println("\n=== Sol ===");
  if (isnan(soil)) {
    M5.Lcd.println("En attente...");
  } else {
    M5.Lcd.printf("Humidite: %.1f %%\n", soil);
  }

  M5.Lcd.println("\n=== Lumiere ===");
  if (isnan(lightV)) {
    M5.Lcd.println("En attente...");
  } else {
    M5.Lcd.printf("Lum: %.1f %%\n", lightV);
  }

  delay(300);
}

