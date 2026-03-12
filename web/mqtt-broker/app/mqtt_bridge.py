import json
import logging
import re
from datetime import datetime

import paho.mqtt.client as mqtt
import mysql.connector
from mysql.connector import Error

# --- Configuration ---
MQTT_BROKER = "mosquitto"
MQTT_PORT = 1883
MQTT_TOPIC = "serre/+/bac/+"

DB_CONFIG = {
    "host": "mosquitto_db",
    "database": "parc",
    "user": "root",         #changer le mot de passe et l'utilisateur pour la production
    "password": "root",
}

SENSOR_MAP = {
    "humiditeAmb": 1,   # ambiantMoisture
    "tempAmb":     2,   # ambiantTemperature
    "humiditeSol": 3,   # soilMoisture
}

TOPIC_PATTERN = re.compile(r"^serre/(\d+)/bac/(\d+)$")

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)


# --- DB helpers ---

def get_connection():
    return mysql.connector.connect(**DB_CONFIG)


def get_bac_id(cursor, serre_numero: int, bac_numero: int):
    cursor.execute(
        """
        SELECT b.id FROM bac b
        JOIN serre s ON b.serre = s.id
        WHERE s.numero = %s AND b.numero = %s
        LIMIT 1
        """,
        (serre_numero, bac_numero),
    )
    row = cursor.fetchone()
    return row[0] if row else None


def insert_mesure(cursor, bac_id: int, capteur_id: int, value: float):
    cursor.execute(
        """
        INSERT INTO mesure (bac, value, measured_at, capteur)
        VALUES (%s, %s, %s, %s)
        """,
        (bac_id, value, datetime.now(), capteur_id),
    )


def insert_error(cursor, error_type: str, message: str, value: str):
    cursor.execute(
        """
        INSERT INTO error (error_type, message, value, occurred_at)
        VALUES (%s, %s, %s, %s)
        """,
        (error_type, message, value, datetime.now()),
    )


def log_and_store_error(conn, error_type: str, message: str, value: str):
    logging.error("error: %s, value: %s", message, value)
    try:
        cursor = conn.cursor()
        insert_error(cursor, error_type, message, value)
        conn.commit()
        cursor.close()
    except Error as db_err:
        logging.error("Failed to write error to DB: %s", db_err)


# --- MQTT callbacks ---

def on_connect(client, userdata, flags, rc):
    if rc == 0:
        logging.info("Connected to MQTT broker, subscribing to %s", MQTT_TOPIC)
        client.subscribe(MQTT_TOPIC)
    else:
        logging.error("MQTT connection failed with code %d", rc)


def on_message(client, userdata, msg):
    conn = userdata["conn"]
    topic = msg.topic

    # Parse topic
    match = TOPIC_PATTERN.match(topic)
    if not match:
        logging.warning("Unexpected topic format: %s", topic)
        return

    serre_numero = int(match.group(1))
    bac_numero   = int(match.group(2))

    # Parse payload
    try:
        payload = json.loads(msg.payload.decode("utf-8"))
    except (json.JSONDecodeError, UnicodeDecodeError) as e:
        log_and_store_error(
            conn,
            "INVALID_PAYLOAD",
            f"Failed to parse JSON on topic {topic}: {e}",
            msg.payload.decode("utf-8", errors="replace"),
        )
        return

    cursor = conn.cursor()
    try:
        # Resolve bac
        bac_id = get_bac_id(cursor, serre_numero, bac_numero)
        if bac_id is None:
            log_and_store_error(
                conn,
                "BAC_NOT_FOUND",
                f"No bac found for serre {serre_numero} bac {bac_numero}",
                str(payload),
            )
            return

        # Process each key in the payload
        for key, value in payload.items():
            capteur_id = SENSOR_MAP.get(key)
            if capteur_id is None:
                log_and_store_error(
                    conn,
                    "UNKNOWN_SENSOR",
                    f"Unknown sensor type '{key}' on topic {topic}",
                    str(value),
                )
                continue

            insert_mesure(cursor, bac_id, capteur_id, float(value))
            logging.info(
                "Inserted mesure: serre=%d bac=%d capteur=%d value=%s",
                serre_numero, bac_numero, capteur_id, value,
            )

        conn.commit()

    except Error as db_err:
        logging.error("DB error while processing message: %s", db_err)
        conn.rollback()
    finally:
        cursor.close()


# --- Entry point ---

def main():
    try:
        conn = get_connection()
        logging.info("Connected to database")
    except Error as e:
        logging.critical("Cannot connect to database: %s", e)
        raise SystemExit(1)

    client = mqtt.Client(userdata={"conn": conn})
    client.on_connect = on_connect
    client.on_message = on_message

    try:
        client.connect(MQTT_BROKER, MQTT_PORT, keepalive=60)
        client.loop_forever()
    except KeyboardInterrupt:
        logging.info("Shutting down")
    finally:
        conn.close()
        client.disconnect()


if __name__ == "__main__":
    main()
