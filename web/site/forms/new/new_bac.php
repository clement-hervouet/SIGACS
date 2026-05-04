<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'errors' => ['Non autorisé.']]);
    exit;
}
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'errors' => ['Méthode invalide.']]);
    exit;
}

// ── Validate ──────────────────────────────────────────────
$id_serre = filter_input(INPUT_POST, 'serre',  FILTER_VALIDATE_INT);
$blocX    = filter_input(INPUT_POST, 'blocX',  FILTER_VALIDATE_INT);
$blocY    = filter_input(INPUT_POST, 'blocY',  FILTER_VALIDATE_INT);
$numero   = filter_input(INPUT_POST, 'numero', FILTER_VALIDATE_INT);

if (!$id_serre || $id_serre < 1) $errors[] = 'Serre manquante.';
if (!$blocX    || $blocX    < 1) $errors[] = 'Taille horizontale invalide.';
if (!$blocY    || $blocY    < 1) $errors[] = 'Taille verticale invalide.';
if (!$numero   || $numero   < 1) $errors[] = 'Numéro de bac invalide.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $pdo = get_pdo('app');

    // ── Security: serre must belong to current user ────────
    $check = $pdo->prepare('
        SELECT id_serre FROM serre
        WHERE id_serre = ? AND cree_par = ?
    ');
    $check->execute([$id_serre, $_SESSION['id']]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'errors' => ['Serre introuvable.']]);
        exit;
    }

    // ── Check numero uniqueness within this serre ──────────
    $dup = $pdo->prepare('
        SELECT id_bac FROM bac
        WHERE serre = ? AND numero = ?
    ');
    $dup->execute([$id_serre, $numero]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'errors' => ['Ce numéro de bac est déjà pris.']]);
        exit;
    }

    // ── Insert ────────────────────────────────────────────
    $stmt = $pdo->prepare('
        INSERT INTO bac (serre, x_taille, y_taille, numero, arrose)
        VALUES (?, ?, ?, ?, 0)
    ');
    $stmt->execute([$id_serre, $blocX, $blocY, $numero]);
    $newId = $pdo->lastInsertId();

    echo json_encode([
        'success'  => true,
        'redirect' => 'content/bac.php?id=' . $newId,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'errors' => ['Erreur serveur.']]);
}