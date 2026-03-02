import os
import json
import time
import paho.mqtt.client as mqtt
import mysql.connector

DB_HOST = os.getenv("DB_HOST")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")
DB_NAME = os.getenv("DB_NAME")

MQTT_HOST = os.getenv("MQTT_HOST")
MQTT_PORT = int(os.getenv("MQTT_PORT"))

def connect_db():
    return mysql.connector.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME
    )

def create_table():
    conn = connect_db()
    cursor = conn.cursor()
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            topic VARCHAR(255),
            payload TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    """)
    conn.commit()
    conn.close()

def on_connect(client, userdata, flags, rc):
    print("Connected to MQTT")
    client.subscribe("#")

def on_message(client, userdata, msg):
    print(f"Received: {msg.topic} -> {msg.payload}")
    conn = connect_db()
    cursor = conn.cursor()
    cursor.execute(
        "INSERT INTO messages (topic, payload) VALUES (%s, %s)",
        (msg.topic, msg.payload.decode())
    )
    conn.commit()
    conn.close()

def main():
    time.sleep(10)  # wait for db
    create_table()

    client = mqtt.Client()
    client.on_connect = on_connect
    client.on_message = on_message

    client.connect(MQTT_HOST, MQTT_PORT, 60)
    client.loop_forever()

if __name__ == "__main__":
    main()