<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'errors' => ['Non autorisé.']]); exit;
}

require_once __DIR__ . '/../config/config.php';

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    echo json_encode(['success' => false, 'errors' => ['ID invalide.']]); exit;
}

$pdo = get_pdo('app');
$stmt = $pdo->prepare('UPDATE error SET acquittee = 1 WHERE id_error = ?');
$stmt->execute([$id]);

echo json_encode(['success' => true]);