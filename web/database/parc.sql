CREATE DATABASE IF NOT EXISTS parc
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE parc;

-- Table: controleur
CREATE TABLE controleur (
    id              INTEGER PRIMARY KEY,
    type            TEXT,
    ip              TEXT,
    status          BOOLEAN
);

-- Table: serre
CREATE TABLE serre (
    id              INTEGER PRIMARY KEY,
    controleur      INTEGER REFERENCES controleur(id),
    nom             TEXT,
    localisation    TEXT,
    surface         FLOAT,
    nbBac           INTEGER,
    numero          INTEGER,
    cree_par        VARCHAR(50) NOT NULL
);

-- Table: bac
CREATE TABLE bac (
    id              INTEGER PRIMARY KEY,
    serre           INTEGER REFERENCES serre(id),
    x_taille        INTEGER,
    y_taille        INTEGER,
    numero          INTEGER,
    arrose          BOOLEAN
);

-- Table: culture
CREATE TABLE culture (
    id              INTEGER PRIMARY KEY,
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
    cree_par        VARCHAR(50) NOT NULL
);

-- Table: capteur
CREATE TABLE capteur (
    id              INTEGER PRIMARY KEY,
    type            TEXT,
    valeurMaxCapteur FLOAT,
    valeurMinCapteur FLOAT,
    unite           TEXT
);

-- Table: bloc
CREATE TABLE bloc (
    id              INTEGER PRIMARY KEY,
    bac             INTEGER REFERENCES bac(id),
    x               INTEGER,
    y               INTEGER,
    empty           BOOLEAN,
    culture         INTEGER REFERENCES culture(id),
    plante_a        DATETIME
);

-- Table: mesure
CREATE TABLE mesure (
    id              INTEGER PRIMARY KEY,
    bac             INTEGER REFERENCES bac(id),
    value           FLOAT,
    mesure_a        DATETIME,
    capteur         INTEGER REFERENCES capteur(id)
);

-- Table: error
-- Log des erreurs runtime du programme MQTT bridge
CREATE TABLE error (
    id              INTEGER PRIMARY KEY AUTO_INCREMENT,
    type_erreur     VARCHAR(64)  NOT NULL,   -- ex: UNKNOWN_SENSOR, BAC_NOT_FOUND, INVALID_PAYLOAD
    message         TEXT         NOT NULL,   -- description lisible de l'erreur
    valeur          TEXT,                    -- valeur brute reçue au moment de l'erreur
    erreur_a        DATETIME     NOT NULL
);