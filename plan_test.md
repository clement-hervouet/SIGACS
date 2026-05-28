# Plan de tests — Système de serre connectée (ESP-NOW → MQTT)

## 1. Démarche de test

Le système repose sur trois firmwares distincts — **nœud air**, **nœud sol** et **gateway** — qui partagent une logique métier commune. La stratégie de validation s'articule autour de deux familles complémentaires :

| Famille | Ce qu'on teste | Matériel requis | Outil |
|---|---|---|---|
| **Tests unitaires** | Logique pure : conversions, parsing, seuils, sérialisation, backoff | Aucun (exécution sur PC) | PlatformIO `native` + Unity |
| **Tests d'intégration** | Chaîne réelle : portée radio, lecture capteurs, connexion TLS | Cartes + broker | Observation des logs série |

La logique pure a été extraite dans un module isolé (`lib/serre_logic/`), sans aucune dépendance Arduino/ESP. Cela permet de l'exécuter automatiquement sur PC en quelques secondes, indépendamment du matériel.

```bash
pio test -e native   # exécute les 14 tests unitaires
```

***

## 2. Tests unitaires (automatisés, sur PC)

### 2.1 Sérialisation ESP-NOW — struct `packed`

| # | Cas de test | Entrée | Résultat attendu |
|---|---|---|---|
| 1.1 | Taille de la structure | `sizeof(SensorData)` | 12 octets (sans padding) |
| 1.2 | Aller-retour octets | struct "air" / 23.7 / 61.2 → buffer → struct | Données identiques après relecture |

> **Pourquoi `__attribute__((packed))` est indispensable :** l'émetteur et le récepteur doivent interpréter les 12 octets de manière strictement identique. Sans cet attribut, le compilateur peut insérer du padding qui rend les deux représentations incompatibles.

### 2.2 Conversion ADC → % humidité sol

| # | Cas de test | Entrée (raw) | Résultat attendu |
|---|---|---|---|
| 2.1 | Sol saturé | `0` | `0 %` |
| 2.2 | Sol sec | `4095` | `100 %` |
| 2.3 | Valeur médiane | `2048` | `≈ 50 %` (±1) |
| 2.4 | Hors plage — bornage | `-500` / `9999` | `0 %` / `100 %` |

> Le `constrain(0, 100)` protège contre les dérives hors étalonnage et garantit une valeur toujours exploitable.

### 2.3 Classification du type de paquet

| # | Cas de test | Entrée (`type`) | Résultat attendu |
|---|---|---|---|
| 3.1 | Paquet air | `"air"` | `AIR` |
| 3.2 | Paquet sol | `"sol"` | `SOL` |
| 3.3 | Type invalide | `"xyz"`, `""` | `UNKNOWN` (paquet rejeté) |

> Ces tests vérifient la robustesse du parsing face à des données corrompues ou inattendues sur le canal ESP-NOW.

### 2.4 Validation snapshot + payload MQTT

| # | Cas de test | Entrée | Résultat attendu |
|---|---|---|---|
| 4.1 | Donnée manquante | Humidité sol = `-999` | Snapshot rejeté — pas de publication |
| 4.2 | Snapshot complet | 3 valeurs valides | Snapshot accepté |
| 4.3 | Format JSON | `22.3 / 55.8 / 40.1` | `{"temperatureAmbiante":22.3,...}` exact |

> La gateway ne publie que lorsque les trois grandeurs ont été reçues **au moins une fois**. La sentinelle `-999` signifie « jamais reçu » ; tout ce qui est en dessous de `-100` est considéré invalide, laissant une marge confortable par rapport aux valeurs physiques réelles.

### 2.5 Backoff exponentiel MQTT

| # | Cas de test | Entrée (backoff actuel) | Résultat attendu |
|---|---|---|---|
| 5.1 | Doublement | `5 000 → 10 000 → 20 000 → 40 000` ms | Double à chaque nouvel échec |
| 5.2 | Plafonnement | `40 000`, `60 000` ms | Plafonné à `60 000 ms` |

> Ce mécanisme évite de saturer le broker lors d'une panne réseau prolongée.

### Résultat global

```
14 tests, 0 failures
```

***

## 3. Tests d'intégration (manuels, avec matériel)

| # | Cas de test | Procédure | Critère de réussite |
|---|---|---|---|
| I.1 | Lecture DHT22 | Souffler / réchauffer le capteur | T et H varient cohéremment dans les logs |
| I.2 | Étalonnage sonde sol | Sonde à sec puis immergée dans l'eau | RAW ≈ 4095 (sec) / ≈ 0 (eau) |
| I.3 | Portée ESP-NOW | Éloigner progressivement le nœud de la gateway | `[SEND] OK` jusqu'à la distance utile, puis `FAIL` |
| I.4 | Retry ESP-NOW | Couper brièvement la gateway | Le nœud retente, puis logue `LOST` |
| I.5 | File d'attente gateway | Couper le broker MQTT | La queue se remplit ; republication à la reconnexion |
| I.6 | Reconnexion MQTT | Couper puis rétablir le réseau | Backoff visible dans les logs, reconnexion automatique |
| I.7 | Deep sleep | Mesurer la période de réveil au chronomètre | Réveil toutes les ≈ 15 s (timer hardware) |

***

## 4. Points de discussion pour l'oral

**Pourquoi tester sur PC ?**
L'exécution sur `native` est plusieurs ordres de grandeur plus rapide qu'un reflash (secondes contre dizaines de secondes), entièrement reproductible, et intégrable dans un pipeline CI/CD sans aucun matériel.

**Limite des tests unitaires**
Ils ne couvrent pas le matériel réel : radio ESP-NOW, ADC physique, handshake TLS. Les tests d'intégration sont donc indispensables en complément pour valider la chaîne de bout en bout.

**Choix de la sentinelle `-999`**
Les valeurs physiques réelles (température, humidité, humidité sol) ne descendent jamais sous `-100` dans des conditions d'utilisation normale. Le seuil d'invalidité à `-100` offre ainsi une marge confortable tout en restant facilement détectable dans le code.
