#include <Arduino.h>
#include <WiFi.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <M5StickC.h>
#include <Adafruit_Sensor.h>
#include <DHT.h>
#include "secrets.h"

#define DHTPIN  33
#define DHTTYPE DHT22

uint8_t gatewayMAC[] = {0x24, 0xD7, 0xEB, 0x38, 0xEE, 0xB4};

typedef struct {
  char type[4];
  float temperature;
  float humidity;
} SensorData;

SensorData dataToSend;
DHT dht(DHTPIN, DHTTYPE);

void onDataSent(const uint8_t* mac, esp_now_send_status_t status) {
  Serial.printf("ESP-NOW envoi : %s\n",
    status == ESP_NOW_SEND_SUCCESS ? "OK" : "ECHEC");
}

void setupESPNow() {
  WiFi.mode(WIFI_STA);
  WiFi.disconnect();

  if (esp_now_init() != ESP_OK) {
    Serial.println("Erreur init ESP-NOW");
    return;
  }

  esp_wifi_set_channel(1, WIFI_SECOND_CHAN_NONE); 

  esp_now_register_send_cb(onDataSent);

  esp_now_peer_info_t peerInfo = {};
  memcpy(peerInfo.peer_addr, gatewayMAC, 6);
  peerInfo.channel = 1;
  peerInfo.encrypt = false;

  if (esp_now_add_peer(&peerInfo) != ESP_OK) {
    Serial.println("Erreur ajout peer ESP-NOW");
  } else {
    Serial.println("ESP-NOW OK - Peer M5Stack enregistre");
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

  strncpy(dataToSend.type, "air", sizeof(dataToSend.type));
  setupESPNow();
}

void loop() {
  M5.update();

  float humAir  = dht.readHumidity();
  float tempAir = dht.readTemperature();

  if (!isnan(humAir) && !isnan(tempAir)) {
    dataToSend.temperature = tempAir;
    dataToSend.humidity    = humAir;

    Serial.printf("T: %.1f C  H: %.1f %%\n", tempAir, humAir);

    esp_err_t result = esp_now_send(gatewayMAC,
                                    (uint8_t*)&dataToSend,
                                    sizeof(dataToSend));
    if (result != ESP_OK) Serial.println("Erreur envoi ESP-NOW");
  } else {
    Serial.println("Erreur lecture DHT22");
  }

  delay(10000);
}