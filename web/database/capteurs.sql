-- requête de population de la table capteur avec les valeurs du projet et des capteurs utilisé

INSERT INTO capteur (id_capteur, type, valeurMinCapteur, valeurMaxCapteur, unite) VALUES
  (1, 'humiditeAmbiante',    0,   100, '%'),
  (2, 'humiditeSol',         0,   100, '%'),
  (3, 'temperatureAmbiante', -20,  80, '°C');
