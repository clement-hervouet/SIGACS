# Parc – MQTT → MySQL Bridge

A lightweight Python service that bridges a Mosquitto MQTT broker to a MySQL
database for the **Parc** greenhouse monitoring system.

---

## Architecture

```
[IoT sensors / controleurs]
        │  MQTT  serre/X/bac/Y
        ▼
 ┌─────────────┐         ┌──────────────────────┐
 │  Mosquitto  │ ───────▶│   bridge.py (Python)  │ ──▶  MySQL (external)
 │  (broker)   │         │   subscribe + persist │
 └─────────────┘         └──────────────────────┘
   Docker container             Docker container
```

Both containers share a private Docker network (`parc-net`).
The MySQL server is external (managed separately).

---

## MQTT topics

| Pattern | Description |
|---------|-------------|
| `serre/<numero>/bac/<numero>` | Measurements from bac `<numero>` inside serre `<numero>` |

### Payload format (JSON)

```json
{ "humiditeAmbiante": 65.3, "temperatureAmbiante": 22.1 }
```

Multiple sensor keys can coexist in a single payload.

### Supported sensors

| JSON key | `id_capteur` |
|---|---|
| `humiditeAmbiante` | 1 |
| `humiditeSol` | 2 |
| `temperatureAmbiante` | 3 |

---

## Error handling

All errors are persisted to the `error` table with one of these `type_erreur` values:

| Code | Trigger |
|---|---|
| `BAC_NOT_FOUND` | Serre or bac number not in the database |
| `UNKNOWN_SENSOR` | Sensor key not in the known list |
| `INVALID_PAYLOAD` | JSON parse error or non-object payload |
| `INVALID_VALUE` | Non-numeric value for a sensor |
| `VALUE_OUT_OF_RANGE` | Value clamped to sensor min/max |
| `INVALID_ENCODING` | Non-UTF-8 payload |
| `MQTT_DISCONNECT` | Unexpected broker disconnection |
| `DB_ERROR` | Transient database query failure |
| `DB_INSERT_ERROR` | Failed to insert a mesure row |
| `UNEXPECTED_ERROR` | Unhandled exception (catch-all) |

When a value is out of range, the clamped value (min or max) is written to
`mesure` **and** an error row is inserted.

---

## Quick start

### 1. Prerequisites

- Docker ≥ 24 with Compose plugin
- An accessible MySQL / MariaDB server with the `parc` schema applied

### 2. Configuration

```bash
cp .env.example .env
# Edit .env and fill in DB_HOST, DB_USER, DB_PASSWORD, etc.
```

### 3. Start

```bash
docker compose up -d --build
```

### 4. Verify

```bash
# Follow bridge logs
docker compose logs -f bridge

# Test with a dummy publish
docker run --rm --network parc-net eclipse-mosquitto:2 \
  mosquitto_pub -h mosquitto -t "serre/1/bac/1" \
  -m '{"humiditeAmbiante": 65, "temperatureAmbiante": 22}'
```

---

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `DB_HOST` | *(required)* | MySQL host |
| `DB_PORT` | `3306` | MySQL port |
| `DB_NAME` | `parc` | Database name |
| `DB_USER` | *(required)* | MySQL user |
| `DB_PASSWORD` | *(required)* | MySQL password |
| `MQTT_HOST` | `mosquitto` | Broker hostname |
| `MQTT_PORT` | `1883` | Broker port |
| `MQTT_KEEPALIVE` | `60` | MQTT keepalive (s) |
| `MQTT_USER` | *(empty)* | Broker username (optional) |
| `MQTT_PASSWORD` | *(empty)* | Broker password (optional) |
| `DB_RETRY_DELAY` | `5` | Seconds between DB retries |
| `MQTT_RETRY_DELAY` | `5` | Seconds between MQTT retries |
| `LOG_LEVEL` | `INFO` | `DEBUG`/`INFO`/`WARNING`/`ERROR` |

---

## File layout

```
.
└── mqtt-bridge
    ├── bridge.py
    ├── docker-compose.yml
    ├── Dockerfile
    ├── mosquitto
    │   ├── client-certs
    │   │   ├── client.crt
    │   │   ├── client.csr
    │   │   └── client.key
    │   ├── clients-certs.sh
    │   ├── config
    │   │   ├── mosquitto.conf
    │   │   └── mosquitto.conf.bak
    │   ├── server-certs
    │   │   ├── ca.crt
    │   │   ├── ca.key
    │   │   ├── ca.srl
    │   │   ├── server.crt
    │   │   ├── server.csr
    │   │   └── server.key
    │   ├── server-certs.sh
    │   └── v3.ext
    ├── README.md
    └── requirements.txt

```
