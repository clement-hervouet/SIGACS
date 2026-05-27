<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['has_alerts' => false]); exit;
}
require_once __DIR__ . '/../../config/config.php';

define('SEUIL_RECURRENCE', 3); // nb min d'occurrences pour déclencher une alarme

$pdo = get_pdo('app');
$stmt = $pdo->prepare('
    SELECT type_erreur, COUNT(*) AS nb, MAX(erreur_a) AS derniere_occurrence
    FROM error
    WHERE acquittee = 0
    GROUP BY type_erreur
    HAVING nb >= ?
    ORDER BY nb DESC
');
$stmt->execute([SEUIL_RECURRENCE]);
$recurrentes = $stmt->fetchAll();

echo json_encode([
    'has_alerts' => count($recurrentes) > 0,
    'count'      => count($recurrentes),
    'errors'     => $recurrentes,
]);