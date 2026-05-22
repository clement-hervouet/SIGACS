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

$bacId     = filter_input(INPUT_POST, 'bac',     FILTER_VALIDATE_INT);
$cultureId = filter_input(INPUT_POST, 'culture', FILTER_VALIDATE_INT);
$x         = filter_input(INPUT_POST, 'x',       FILTER_VALIDATE_INT);
$y         = filter_input(INPUT_POST, 'y',       FILTER_VALIDATE_INT);

if (!$bacId || !$cultureId || $x === false || $y === false) {
    echo json_encode(['success' => false, 'errors' => ['Paramètres invalides.']]);
    exit;
}

try {
    $pdo = get_pdo('app');

    // Verify bac belongs to current user
    $check = $pdo->prepare('
        SELECT b.id_bac
        FROM bac b
        JOIN serre s ON s.id_serre = b.serre
        WHERE b.id_bac = ? AND s.cree_par = ?
    ');
    $check->execute([$bacId, $_SESSION['id']]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'errors' => ['Bac introuvable.']]);
        exit;
    }

    // Verify culture belongs to current user
    $checkC = $pdo->prepare('
        SELECT id_culture FROM culture
        WHERE id_culture = ? AND cree_par = ?
    ');
    $checkC->execute([$cultureId, $_SESSION['id']]);
    if (!$checkC->fetch()) {
        echo json_encode(['success' => false, 'errors' => ['Culture introuvable.']]);
        exit;
    }

    // Verify bloc not already filled at (x, y)
    $dup = $pdo->prepare('
        SELECT id_bloc FROM bloc
        WHERE bac = ? AND x = ? AND y = ?
    ');
    $dup->execute([$bacId, $x, $y]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'errors' => ['Ce bloc est déjà occupé.']]);
        exit;
    }

    // Insert bloc
    $stmt = $pdo->prepare('
        INSERT INTO bloc (bac, x, y, culture, plante_a)
        VALUES (?, ?, ?, ?, NOW())
    ');
    $stmt->execute([$bacId, $x, $y, $cultureId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
}