-- you should have to run this SQL only once, to create the the table and it's associated columns. You can run this SQL after creating the database (named as you wish)
CREATE DATABASE IF NOT EXISTS user_base
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE user_base;

CREATE TABLE users (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    role     ENUM('admin','lecteur','editeur') DEFAULT 'lecteur'
    nom VARCHAR(50) NOT NULL,
    prenom VARCHAR(50) NOT NULL
);