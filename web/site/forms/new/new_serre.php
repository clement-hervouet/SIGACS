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
$nom     = trim($_POST['nom']      ?? '');
$adresse = trim($_POST['addresse'] ?? '');
$surface = filter_input(INPUT_POST, 'surface', FILTER_VALIDATE_FLOAT);
$nbBac   = filter_input(INPUT_POST, 'bac',     FILTER_VALIDATE_INT);

if (strlen($nom) < 5)              $errors[] = 'Nom invalide (5 caractères minimum).';
if ($surface === false || $surface < 0.1) $errors[] = 'Surface invalide.';
if ($nbBac   === false || $nbBac   < 1)  $errors[] = 'Nombre de bacs invalide.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ── Insert ────────────────────────────────────────────────
try {
    $pdo = get_pdo('app');
    $stmt = $pdo->prepare('
        INSERT INTO serre (nom, localisation, surface, nbBac, cree_par)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $nom,
        $adresse ?: null,
        $surface,
        $nbBac,
        $_SESSION['id']
    ]);
    $newId = $pdo->lastInsertId();

    // Auto-create bac rows
    $stmtBac = $pdo->prepare('
        INSERT INTO bac (serre, numero, x_taille, y_taille, arrose)
        VALUES (?, ?, 1, 1, 0)
    ');
    for ($i = 1; $i <= $nbBac; $i++) {
        $stmtBac->execute([$newId, $i]);
    }

    echo json_encode([
        'success'  => true,
        'redirect' => 'content/serre.php?id=' . $newId,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
}