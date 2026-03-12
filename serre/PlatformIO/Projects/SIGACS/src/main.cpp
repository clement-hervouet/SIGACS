#include <M5Unified.h>
#include "Grove_LED_Bar.h" 
#include "DHT.h"    

#define SOIL_PIN   36   
#define LIGHT_PIN  39   
#define DHTPIN     32  
#define DHTTYPE DHT22

DHT dht(DHTPIN, DHTTYPE);

void setup() {
    auto cfg = M5.config();
    M5.begin(cfg);
    Serial.begin(115200);

    dht.begin();
}

void loop() {

    // 1) TEMP & HUM AIR (DHT22)

    float humAir = dht.readHumidity();
    float tempAir = dht.readTemperature();

    if (isnan(humAir) || isnan(tempAir)) {
        Serial.println("Erreur DHT22");
    }
    // 2) HUMIDITÉ DU SOL (%) Moisture v1.4
    int rawSoil = analogRead(SOIL_PIN);
    float soilPercent = map(rawSoil, 300, 950, 0, 100);
    soilPercent = constrain(soilPercent, 0, 100);

    // 3) LUMINOSITÉ (%) Light v1.2
    int rawLight = analogRead(LIGHT_PIN);
    float lightPercent = (rawLight / 4095.0f) * 100.0f;

    // 4) AFFICHAGE
    Serial.println("====================================");

    Serial.print("Température Air : ");
    Serial.print(tempAir);
    Serial.println(" °C");

    Serial.print("Humidité Air : ");
    Serial.print(humAir);
    Serial.println(" %");

    Serial.print("Humidité Sol : ");
    Serial.print(soilPercent);
    Serial.println(" %");

    Serial.print("Luminosité : ");
    Serial.print(lightPercent);
    Serial.println(" %");

    delay(1000);
}


