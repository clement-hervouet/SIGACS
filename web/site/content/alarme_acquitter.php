<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'errors' => ['Non autorisé.']]); exit;
}

require_once __DIR__ . '/../config/config.php';
$pdo = get_pdo('app');

// Acquittement en lot
if (!empty($_POST['ids'])) {
    $ids = json_decode($_POST['ids'], true);
    if (!is_array($ids) || empty($ids)) {
        echo json_encode(['success' => false, 'errors' => ['Liste d\'IDs invalide.']]); exit;
    }
    $ids = array_filter($ids, 'is_int');
    if (empty($ids)) {
        echo json_encode(['success' => false, 'errors' => ['IDs invalides.']]); exit;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("UPDATE error SET acquittee = 1 WHERE id_error IN ($placeholders)");
    $stmt->execute(array_values($ids));
    echo json_encode(['success' => true]);
    exit;
}

// Acquittement unitaire (comportement existant)
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    echo json_encode(['success' => false, 'errors' => ['ID invalide.']]); exit;
}
$stmt = $pdo->prepare('UPDATE error SET acquittee = 1 WHERE id_error = ?');
$stmt->execute([$id]);
echo json_encode(['success' => true]);