CREATE DATABASE IF NOT EXISTS parc
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE parc;

-- Table: controleur
CREATE TABLE controleur (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip        TEXT         NOT NULL,
  type      TEXT         NOT NULL,
  status    INT          NOT NULL DEFAULT 1
);

-- Table: serre
CREATE TABLE serre (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero         INT          NOT NULL,
  nom            TEXT         NOT NULL,
  localisation   TEXT         NOT NULL,
  surface        INT          NOT NULL,
  nbBacs         INT          NOT NULL,
  controleur_id  INT UNSIGNED NOT NULL,
  CONSTRAINT fk_serre_controleur
    FOREIGN KEY (controleur_id) REFERENCES controleur(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
);

-- Table: capteur
CREATE TABLE capteur (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type           TEXT         NOT NULL,
  maxValue       INT          NOT NULL,
  minValue       INT          NOT NULL,
  unite          TEXT         NOT NULL,
  controleur_id  INT UNSIGNED NOT NULL,
  CONSTRAINT fk_capteur_controleur
    FOREIGN KEY (controleur_id) REFERENCES controleur(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
);

-- Table: record
CREATE TABLE record (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  value       INT           NOT NULL,
  timestamp   TIME          NOT NULL DEFAULT (CURRENT_TIME),
  date        DATE          NOT NULL DEFAULT (CURRENT_DATE),
  topic       VARCHAR(255)  NOT NULL,
  payload     TEXT          NOT NULL,
  broker_ip   VARCHAR(45)   NOT NULL,
  retain      TINYINT(1)    NOT NULL DEFAULT 0,
  made_by     INT UNSIGNED  NOT NULL,
  CONSTRAINT fk_record_capteur
    FOREIGN KEY (made_by) REFERENCES capteur(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
);

-- Table: bac
CREATE TABLE bac (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  numero      INT           NOT NULL,
  x_size      INT           NOT NULL,
  y_size      INT           NOT NULL,
  serre_id    INT UNSIGNED  NOT NULL,
  capteur_id  INT UNSIGNED  NOT NULL,
  CONSTRAINT fk_bac_serre
    FOREIGN KEY (serre_id) REFERENCES serre(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_bac_capteur
    FOREIGN KEY (capteur_id) REFERENCES capteur(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
);

-- Table: bloc
CREATE TABLE bloc (
  id          INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  x           INT           NOT NULL,
  y           INT           NOT NULL,
  empty       BOOLEAN       NOT NULL DEFAULT TRUE,
  culture_id  INT UNSIGNED,
  moisture    INT,
  planted_at  DATETIME,
  bac_id      INT UNSIGNED  NOT NULL,
  CONSTRAINT fk_bloc_bac
    FOREIGN KEY (bac_id) REFERENCES bac(id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_bloc_culture
    FOREIGN KEY (culture_id) REFERENCES culture(id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT uq_bloc_position UNIQUE (bac_id, x, y)
);

-- Table: culture
CREATE TABLE culture (
  id            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  plante        TEXT          NOT NULL,
  plante_latin  TEXT          NOT NULL,
  humMinAmb     FLOAT         NOT NULL,
  humMaxAmb     FLOAT         NOT NULL,
  humMinSol     FLOAT         NOT NULL,
  humMaxSol     FLOAT         NOT NULL,
  tempMin       FLOAT         NOT NULL,
  tempMax       FLOAT         NOT NULL,
  tempsPousse   INT           NOT NULL
);