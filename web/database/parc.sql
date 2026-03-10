CREATE DATABASE IF NOT EXISTS parc
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE parc;

-- Table: controleur
CREATE TABLE controleur (
    id INTEGER PRIMARY KEY,
    type TEXT,
    ip TEXT,
    status BOOLEAN
);

-- Table: serre
CREATE TABLE serre (
    id INTEGER PRIMARY KEY,
    controleur INTEGER REFERENCES controleur(id),
    nom TEXT,
    localisation TEXT,
    surface FLOAT,
    nbBac INTEGER,
    numero INTEGER
);

-- Table: bac
CREATE TABLE bac (
    id INTEGER PRIMARY KEY,
    serre INTEGER REFERENCES serre(id),
    x_size INTEGER,
    y_size INTEGER,
    numero INTEGER
);

-- Table: culture
CREATE TABLE culture (
    id INTEGER PRIMARY KEY,
    plante TEXT,
    plante_latin TEXT,
    humMinAmb FLOAT,
    humMaxAmb FLOAT,
    humMinSol FLOAT,
    humMaxSol FLOAT,
    tempMin FLOAT,
    tempMax FLOAT,
    tempsPousse INTEGER
);

-- Table: capteur
CREATE TABLE capteur (
    id INTEGER PRIMARY KEY,
    type TEXT,
    maxSensorValue FLOAT,
    minSensorValue FLOAT,
    unite TEXT
);

-- Table: bloc
CREATE TABLE bloc (
    id INTEGER PRIMARY KEY,
    bac INTEGER REFERENCES bac(id),
    x INTEGER,
    y INTEGER,
    empty BOOLEAN,
    culture INTEGER REFERENCES culture(id),  -- FK instead of TEXT
    planted_at DATETIME
);

-- Table: mesure
CREATE TABLE mesure (
    id INTEGER PRIMARY KEY,
    bac INTEGER REFERENCES bac(id),
    value FLOAT,
    measured_at DATETIME,                    -- merged date + time
    capteur INTEGER REFERENCES capteur(id)   -- proper FK
);