
#include <Arduino.h>
#include <WiFi.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <esp_sleep.h>
#include <M5StickCPlus.h>
#include <DHT.h>

#define DHTPIN          33
#define DHTTYPE         DHT22
#define ESPNOW_CHANNEL  1
#define SLEEP_US        15000000ULL // 15 s
#define MAX_RETRIES     2
#define ACK_TIMEOUT_MS  200

uint8_t gatewayMAC[] = {0x24, 0xD7, 0xEB, 0x38, 0xDC, 0x38};

typedef struct __attribute__((packed)) {
  char  type[4];
  float temperature;
  float humidity;
} SensorData;

SensorData dataToSend;
DHT dht(DHTPIN, DHTTYPE);

volatile bool sendDone = false;
volatile bool sendOK   = false;

void onDataSent(const uint8_t* mac, esp_now_send_status_t status) {
  sendOK   = (status == ESP_NOW_SEND_SUCCESS);
  sendDone = true;
}

void setupESPNow() {
  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  WiFi.setSleep(false);

  esp_wifi_set_promiscuous(true);
  esp_wifi_set_channel(ESPNOW_CHANNEL, WIFI_SECOND_CHAN_NONE);
  esp_wifi_set_promiscuous(false);

  if (esp_now_init() != ESP_OK) return;
  esp_now_register_send_cb(onDataSent);

  esp_now_peer_info_t peerInfo = {};
  memcpy(peerInfo.peer_addr, gatewayMAC, 6);
  peerInfo.channel = ESPNOW_CHANNEL;
  peerInfo.encrypt = false;
  peerInfo.ifidx   = WIFI_IF_STA;
  esp_now_add_peer(&peerInfo);
}

bool sendWithRetry() {
  for (int attempt = 0; attempt <= MAX_RETRIES; attempt++) {
    sendDone = false;
    sendOK   = false;

    esp_err_t err = esp_now_send(gatewayMAC, (uint8_t*)&dataToSend, sizeof(dataToSend));
    if (err != ESP_OK) {
      Serial.printf("[SEND] esp_now_send err=%d (try %d)\n", err, attempt + 1);
      continue;
    }

    unsigned long t0 = millis();
    while (!sendDone && (millis() - t0) < ACK_TIMEOUT_MS) {
      delay(1);
    }

    if (sendOK) {
      Serial.printf("[SEND] OK try %d (%lums)\n", attempt + 1, millis() - t0);
      return true;
    }
    Serial.printf("[SEND] FAIL try %d\n", attempt + 1);
    delay(20);
  }
  return false;
}

void enterDeepSleep() {
  esp_wifi_stop();
  esp_sleep_enable_timer_wakeup(SLEEP_US);
  esp_deep_sleep_start();
}

void setup() {
  Serial.begin(115200);
  setCpuFrequencyMhz(80);
  btStop();

  M5.begin(false, true, false); 
  M5.Axp.SetLDO2(false);  
  M5.Axp.SetLDO3(false);  

  pinMode(DHTPIN, INPUT);
  dht.begin();

  strncpy(dataToSend.type, "air", sizeof(dataToSend.type));
  setupESPNow();

  Serial.println("[BOOT] Air ready | CPU:80MHz | BT:OFF | LCD:OFF | Sleep:15s");
}
void loop() {
  float humAir  = dht.readHumidity();
  float tempAir = dht.readTemperature();

  if (!isnan(humAir) && !isnan(tempAir)) {
    dataToSend.temperature = tempAir;
    dataToSend.humidity    = humAir;

    bool ok = sendWithRetry();
    Serial.printf("[DATA] T:%.1f H:%.1f [%s] | Heap:%u\n",
                  tempAir, humAir, ok ? "SENT" : "LOST", esp_get_free_heap_size());
  } else {
    Serial.println("[DHT] Lecture invalide (NaN)");
  }

  enterDeepSleep();
}
