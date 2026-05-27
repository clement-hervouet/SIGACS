/**
 * @file    soil_sensor.cpp
 * @brief   Nœud capteur sol — mesure l'humidité du sol via une sonde capacitive
 *          et l'envoie à la gateway via ESP-NOW, puis entre en deep sleep.
 *
 * Cycle de vie :
 *   setup() → loop() → readSoilRaw() → sendWithRetry() → enterDeepSleep() → [réveil timer 15 s] → loop()
 *
 * Optimisations énergie :
 *   - CPU cadencé à 80 MHz (au lieu de 240 MHz par défaut)
 *   - Bluetooth coupé
 *   - Écran LCD éteint (LDO2 coupé via AXP192)
 *   - Deep sleep entre chaque mesure (≈ 15 s)
 */

#include <Arduino.h>
#include <WiFi.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <esp_sleep.h>
#include <M5StickCPlus.h>

#define SOIL_PIN         32            ///< GPIO ADC connecté au signal de la sonde capacitive
#define SOIL_DRY         4095          ///< Valeur ADC brute pour un sol sec (12 bits)
#define SOIL_WET         0             ///< Valeur ADC brute pour un sol saturé
#define ESPNOW_CHANNEL   1
#define SLEEP_US         15000000ULL
#define MAX_RETRIES      2
#define ACK_TIMEOUT_MS   200

uint8_t gatewayMAC[] = {0x24, 0xD7, 0xEB, 0x38, 0xDC, 0x38};

/**
 * @brief Structure partagée entre les nœuds et la gateway.
 *        Attribut packed : aucun octet de padding, taille identique des deux côtés.
 */
typedef struct __attribute__((packed)) {
  char  type[4];       ///< "sol\0"
  float temperature;
  float humidity;
} SensorData;

SensorData dataToSend;

volatile bool sendDone = false;
volatile bool sendOK   = false;

/**
 * @brief Callback ESP-NOW appelé après chaque tentative d'envoi.
 *
 * Exécutée dans le contexte de la tâche Wi-Fi (ISR-like).
 * Met à jour les flags volatils lus par sendWithRetry() dans la boucle d'attente.
 *
 * @param mac     Adresse MAC du destinataire (non utilisée ici).
 * @param status  ESP_NOW_SEND_SUCCESS si la gateway a acquitté le paquet.
 */
void onDataSent(const uint8_t* mac, esp_now_send_status_t status) {
  sendOK   = (status == ESP_NOW_SEND_SUCCESS);
  sendDone = true;
}

/**
 * @brief Initialise la couche ESP-NOW et enregistre la gateway comme pair.
 *
 * - Passe en mode STA sans association à un AP (ESP-NOW fonctionne seul).
 * - Force le canal via le mode promiscuous pour contourner la restriction
 *   qui empêche de changer de canal en mode STA normal.
 * - Enregistre onDataSent() comme callback d'envoi.
 */
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

/**
 * @brief Envoie dataToSend à la gateway avec jusqu'à MAX_RETRIES tentatives.
 *
 * Après chaque esp_now_send(), attend le callback onDataSent() jusqu'à
 * ACK_TIMEOUT_MS millisecondes. Si le ACK n'arrive pas dans ce délai ou
 * indique un échec, retente après 20 ms.
 *
 * @return true  Si la gateway a acquitté le paquet lors d'une des tentatives.
 * @return false Si toutes les tentatives ont échoué.
 */
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

/**
 * @brief Moyenne 5 lectures ADC de la sonde capacitive pour atténuer le bruit.
 *
 * @return Valeur brute ADC moyennée (0 – 4095 en 12 bits).
 */
int readSoilRaw() {
  long sum = 0;
  for (int i = 0; i < 5; i++) {
    sum += analogRead(SOIL_PIN);
    delay(10);
  }
  return sum / 5;
}

/**
 * @brief Coupe le Wi-Fi et entre en deep sleep pour SLEEP_US microsecondes.
 *
 * L'arrêt explicite du Wi-Fi avant le sleep permet de réduire la consommation
 * pendant le deep sleep (le driver est proprement terminé).
 * Au réveil, l'ESP32 redémarre depuis le début de setup().
 */
void enterDeepSleep() {
  esp_wifi_stop();
  esp_sleep_enable_timer_wakeup(SLEEP_US);
  esp_deep_sleep_start();
}

/**
 * @brief Initialisation unique au démarrage / réveil du deep sleep.
 *
 * - Réduit la fréquence CPU à 80 MHz pour économiser de l'énergie.
 * - Éteint le Bluetooth (inutilisé) et l'écran LCD (LDO2 de l'AXP192).
 * - Configure l'ADC en atténuation 11 dB pour couvrir la plage 0–3,3 V.
 * - Prépare le type de paquet ("sol") une seule fois.
 */
void setup() {
  Serial.begin(115200);
  setCpuFrequencyMhz(80);
  btStop();

  M5.begin(false, true, false);
  M5.Axp.SetLDO2(false);
  analogSetAttenuation(ADC_11db);
  pinMode(SOIL_PIN, INPUT);

  strncpy(dataToSend.type, "sol", sizeof(dataToSend.type));
  setupESPNow();

  Serial.println("[BOOT] Sol ready | CPU:80MHz | BT:OFF | LCD:OFF | Sleep:15s");
}

/**
 * @brief Lit la sonde sol, convertit la valeur ADC en % d'humidité et entre en deep sleep.
 *
 * Exécutée une seule fois par cycle de réveil (enterDeepSleep() ne retourne jamais).
 * La valeur brute est mappée linéairement entre SOIL_WET et SOIL_DRY puis
 * contrainte dans [0, 100] pour absorber les dérives hors plage d'étalonnage.
 * Le champ temperature est mis à 0 (non applicable pour ce nœud).
 */
void loop() {
  int raw      = readSoilRaw();
  int humidity = map(raw, SOIL_WET, SOIL_DRY, 0, 100);
  humidity     = constrain(humidity, 0, 100);

  dataToSend.temperature = 0;
  dataToSend.humidity    = (float)humidity;

  bool ok = sendWithRetry();
  Serial.printf("[DATA] RAW:%d Sol:%d%% [%s] | Heap:%u\n",
                raw, humidity, ok ? "SENT" : "LOST", esp_get_free_heap_size());

  enterDeepSleep();
}
