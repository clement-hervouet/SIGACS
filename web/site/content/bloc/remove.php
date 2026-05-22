<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Non autorisé.']]);
    exit;
}
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'errors' => ['Méthode invalide.']]);
    exit;
}

$blocId = filter_input(INPUT_POST, 'bloc_id', FILTER_VALIDATE_INT);

if (!$blocId) {
    echo json_encode(['success' => false, 'errors' => ['Bloc invalide.']]);
    exit;
}

try {
    $pdo = get_pdo('app');

    // Verify ownership via bac→serre→cree_par before deleting
    $check = $pdo->prepare('
        SELECT bl.id_bloc
        FROM bloc bl
        JOIN bac b  ON b.id_bac     = bl.bac
        JOIN serre s ON s.id_serre  = b.serre
        WHERE bl.id_bloc = ? AND s.cree_par = ?
    ');
    $check->execute([$blocId, $_SESSION['id']]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'errors' => ['Bloc introuvable.']]);
        exit;
    }

    $pdo->prepare('DELETE FROM bloc WHERE id_bloc = ?')->execute([$blocId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
}