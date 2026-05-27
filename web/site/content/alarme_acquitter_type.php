<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'errors' => ['Non autorisé.']]); exit;
}
require_once __DIR__ . '/../config/config.php';

$type = filter_input(INPUT_POST, 'type_erreur', FILTER_SANITIZE_SPECIAL_CHARS);
if (!$type) {
    echo json_encode(['success' => false, 'errors' => ['Type invalide.']]); exit;
}

$pdo = get_pdo('app');
$stmt = $pdo->prepare('UPDATE error SET acquittee = 1 WHERE type_erreur = ? AND acquittee = 0');
$stmt->execute([$type]);

echo json_encode(['success' => true]);