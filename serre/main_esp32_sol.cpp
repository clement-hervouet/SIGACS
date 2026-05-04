#include <Arduino.h>
#include <WiFi.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <M5StickC.h>
#include "secrets.h"

#define SOIL_PIN 32
#define SOIL_DRY 4095
#define SOIL_WET 0
#define ESPNOW_CHANNEL 6

uint8_t gatewayMAC[] = {0x24, 0xD7, 0xEB, 0x38, 0xDC, 0x38};

typedef struct __attribute__((packed))
{
  char type[4];
  float temperature;
  float humidity;
} SensorData;

SensorData dataToSend;

void onDataSent(const uint8_t *mac, esp_now_send_status_t status)
{
  Serial.printf("ESP-NOW envoi : %s\n",
                status == ESP_NOW_SEND_SUCCESS ? "OK" : "ECHEC");
}
void connectWiFi()
{
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("Connexion WiFi");
  M5.Lcd.println("WiFi...");
  while (WiFi.status() != WL_CONNECTED)
  {
    delay(500);
    Serial.print(".");
  }
  Serial.printf("\nWiFi OK\nIP : %s\nCanal : %d\n",
                WiFi.localIP().toString().c_str(), WiFi.channel());
  M5.Lcd.printf("WiFi OK\nIP:%s\nCanal:%d\n",
                WiFi.localIP().toString().c_str(), WiFi.channel());
}

void setupESPNow()
{
  connectWiFi();

      if (esp_now_init() != ESP_OK)
  {
    Serial.println("Erreur init ESP-NOW");
    return;
  }
  esp_now_register_send_cb(onDataSent);

  esp_now_peer_info_t peerInfo = {};
  memcpy(peerInfo.peer_addr, gatewayMAC, 6);
  peerInfo.channel = ESPNOW_CHANNEL;
  peerInfo.encrypt = false;
  peerInfo.ifidx = WIFI_IF_STA;

  if (esp_now_add_peer(&peerInfo) != ESP_OK)
  {
    Serial.println("Erreur ajout peer ESP-NOW");
  }
  else
  {
    Serial.println("ESP-NOW OK - Peer M5Stack enregistre");
  }
}

void setup()
{
  Serial.begin(115200);
  delay(1000);
  M5.begin();
  M5.Lcd.setRotation(3);
  M5.Lcd.fillScreen(BLACK);
  M5.Axp.SetLDO2(false);
  analogSetAttenuation(ADC_11db);
  pinMode(SOIL_PIN, INPUT);

  strncpy(dataToSend.type, "sol", sizeof(dataToSend.type));
  setupESPNow();
}

void loop()
{
  M5.update();

  int raw = analogRead(SOIL_PIN);
  int humidity = map(raw, SOIL_WET, SOIL_DRY, 0, 100);
  humidity = constrain(humidity, 0, 100);

  dataToSend.temperature = 0;
  dataToSend.humidity = (float)humidity;

  Serial.printf("RAW : %d  ->  Humidite Sol : %d %%\n", raw, humidity);

  esp_err_t result = esp_now_send(gatewayMAC, (uint8_t *)&dataToSend, sizeof(dataToSend));

  if (result != ESP_OK)
  {
    if (result == ESP_ERR_ESPNOW_NOT_FOUND)
      Serial.println("Erreur: Peer non trouve");
    else if (result == ESP_ERR_ESPNOW_IF)
      Serial.println("Erreur: Mauvaise interface WiFi");
    else
      Serial.printf("Erreur envoi ESP-NOW : %d\n", result);
  }

  delay(10000);
}