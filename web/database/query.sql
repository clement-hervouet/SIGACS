-- Pour récupérer les infos complètes d'un bloc :
SELECT b.x, b.y, b.moisture, b.planted_at, c.*
FROM bloc b
LEFT JOIN culture c ON b.culture_id = c.id --LEFT JOIN et non JOIN pour que les blocs vides (sans culture) remontent quand même dans les résultats.
WHERE b.bac_id = 1;

-- Pour interroger les capteurs d'un bac donné:
SELECT cap.* FROM capteur cap
JOIN capteur_bac cb ON cb.capteur_id = cap.id
WHERE cb.bac_id = 1;

-- La requête pour détecter les cultures à maturité :
SELECT b.*, c.plante, c.tempsPousse,
  DATEDIFF(NOW(), b.planted_at) AS jours_ecoules
FROM bloc b
JOIN culture c ON b.culture_id = c.id
WHERE DATEDIFF(NOW(), b.planted_at) >= c.tempsPousse;