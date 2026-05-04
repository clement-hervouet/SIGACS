#include <Arduino.h>
#include <WiFi.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <M5StickC.h>
#include <Adafruit_Sensor.h>
#include <DHT.h>
#include "secrets.h"

#define DHTPIN 33
#define DHTTYPE DHT22
#define ESPNOW_CHANNEL 6

uint8_t gatewayMAC[] = {0x24, 0xD7, 0xEB, 0x38, 0xDC, 0x38};

typedef struct __attribute__((packed))
{
  char type[4];
  float temperature;
  float humidity;
} SensorData;

SensorData dataToSend;
DHT dht(DHTPIN, DHTTYPE);

void onDataSent(const uint8_t* mac, esp_now_send_status_t status) {
  if (status == ESP_NOW_SEND_SUCCESS) {
    Serial.println("[ESP-NOW]  Envoi OK");
    return;
  }

  // --- ECHEC : on diagnostique ---
  Serial.println("[ESP-NOW]  ECHEC - Diagnostic :");

  // 1. Le peer est-il toujours enregistré ?
  if (!esp_now_is_peer_exist(mac)) {
    Serial.println("  → Peer introuvable (jamais ajouté ou perdu)");
  } else {
    Serial.println("  → Peer OK (enregistré)");
  }

  // 2. Canal courant du WiFi
  uint8_t primaryChan;
  wifi_second_chan_t secondChan;
  esp_wifi_get_channel(&primaryChan, &secondChan);
  Serial.printf("  → Canal WiFi actuel : %d (attendu : %d)\n",
                primaryChan, ESPNOW_CHANNEL);
  if (primaryChan != ESPNOW_CHANNEL) {
    Serial.println("CANAL DIFFERENT → cause probable de l'echec !");
  }

  // 3. Statut WiFi
  Serial.printf("  → WiFi status : %d  (3=connecté, 0=idle)\n",
                (int)WiFi.status());

  // 4. MAC cible (pour confirmer qu'on envoie au bon)
  Serial.printf("  → MAC cible : %02X:%02X:%02X:%02X:%02X:%02X\n",
                mac[0], mac[1], mac[2], mac[3], mac[4], mac[5]);

  // 5. Mémoire heap restante (saturation = envoi impossible)
  Serial.printf("  → Heap libre : %d octets\n", esp_get_free_heap_size());
}

void setupESPNow()
{
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);


  // Forcer le canal proprement
  esp_wifi_set_promiscuous(true);
  esp_wifi_set_channel(ESPNOW_CHANNEL, WIFI_SECOND_CHAN_NONE);
  esp_wifi_set_promiscuous(false);

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
  dht.begin();

  strncpy(dataToSend.type, "air", sizeof(dataToSend.type));
  setupESPNow();
}

void loop()
{
  M5.update();

  float humAir = dht.readHumidity();
  float tempAir = dht.readTemperature();

  if (!isnan(humAir) && !isnan(tempAir))
  {
    dataToSend.temperature = tempAir;
    dataToSend.humidity = humAir;

    Serial.printf("T: %.1f C  H: %.1f %%\n", tempAir, humAir);

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
  }
  else
  {
    Serial.println("Erreur lecture DHT22");
  }

  delay(10000);
}