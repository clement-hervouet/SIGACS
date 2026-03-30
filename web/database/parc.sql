CREATE DATABASE IF NOT EXISTS parc
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE parc;

CREATE TABLE users (
    id_utilisateur  INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    identifiant     VARCHAR(50) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    role            ENUM('admin','lecteur','editeur') DEFAULT 'lecteur',
    nom             VARCHAR(50) NOT NULL,
    prenom          VARCHAR(50) NOT NULL
);

-- Table: controleur
CREATE TABLE controleur (
    id_controleur   INTEGER PRIMARY KEY,
    type            TEXT,
    ip              TEXT,
    status          BOOLEAN
);

-- Table: serre
CREATE TABLE serre (
    id_serre        INTEGER PRIMARY KEY,
    controleur      INTEGER REFERENCES controleur(id_controleur),
    nom             TEXT,
    localisation    TEXT,
    surface         FLOAT,
    nbBac           INTEGER,
    numero          INTEGER,
    cree_par        VARCHAR(50) REFERENCES users(identifiant)
);

-- Table: bac
CREATE TABLE bac (
    id_bac          INTEGER PRIMARY KEY,
    serre           INTEGER REFERENCES serre(id_serre),
    x_taille        INTEGER,
    y_taille        INTEGER,
    numero          INTEGER,
    arrose          BOOLEAN
);

-- Table: culture
CREATE TABLE culture (
    id_culture      INTEGER PRIMARY KEY,
    plante          TEXT,
    plante_latin    TEXT,
    humMinAmb       FLOAT,
    humMaxAmb       FLOAT,
    humOptAmb       FLOAT,
    humMinSol       FLOAT,
    humMaxSol       FLOAT,
    humOptSol       FLOAT,
    tempMin         FLOAT,
    tempMax         FLOAT,
    tempOpt         FLOAT,
    tempsPousse     INTEGER,
    cree_par        VARCHAR(50) REFERENCES users(identifiant)
);

-- Table: capteur
CREATE TABLE capteur (
    id_capteur      INTEGER PRIMARY KEY,
    type            TEXT,
    valeurMaxCapteur FLOAT,
    valeurMinCapteur FLOAT,
    unite           TEXT
);

-- Table: bloc
CREATE TABLE bloc (
    id_bloc         INTEGER PRIMARY KEY,
    bac             INTEGER REFERENCES bac(id_bac),
    x               INTEGER,
    y               INTEGER,
    vide            BOOLEAN,
    culture         INTEGER REFERENCES culture(id_culture),
    plante_a        DATETIME
);

-- Table: mesure
CREATE TABLE mesure (
    id_mesure       INTEGER PRIMARY KEY,
    bac             INTEGER REFERENCES bac(id_bac),
    value           FLOAT,
    mesure_a        DATETIME,
    capteur         INTEGER REFERENCES capteur(id_capteur)
);

-- Table: error
-- Log des erreurs runtime du programme MQTT bridge
CREATE TABLE error (
    id_error        INTEGER PRIMARY KEY AUTO_INCREMENT,
    type_erreur     VARCHAR(64)  NOT NULL,   -- ex: UNKNOWN_SENSOR, BAC_NOT_FOUND, INVALID_PAYLOAD
    message         TEXT         NOT NULL,   -- description lisible de l'erreur
    valeur          TEXT,                    -- valeur brute reçue au moment de l'erreur
    erreur_a        DATETIME     NOT NULL
);