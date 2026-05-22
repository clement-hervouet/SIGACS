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
$f = fn($k) => filter_input(INPUT_POST, $k, FILTER_VALIDATE_FLOAT);

$plante      = trim($_POST['nom']       ?? '');
$latin       = trim($_POST['nom_latin'] ?? '');
$humMinAmb   = $f('humMinAmb');
$humMaxAmb   = $f('humMaxAmb');
$humOptAmb   = $f('humOptAmb');
$humMinSol   = $f('humMinSol');
$humMaxSol   = $f('humMaxSol');
$humOptSol   = $f('humOptSol');
$tempMin     = $f('tempMin');
$tempMax     = $f('tempMax');
$tempOpt     = $f('tempOpt');
$tempsPousse = filter_input(INPUT_POST, 'temps', FILTER_VALIDATE_INT);

if (strlen($plante) < 2) $errors[] = 'Nom invalide (2 caractères minimum).';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ── Insert ────────────────────────────────────────────────
try {
    $pdo = get_pdo('app');
    $stmt = $pdo->prepare('
        INSERT INTO culture (
            plante, plante_latin,
            humMinAmb, humMaxAmb, humOptAmb,
            humMinSol, humMaxSol, humOptSol,
            tempMin,   tempMax,   tempOpt,
            tempsPousse, cree_par
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ');
    $stmt->execute([
        $plante,
        $latin       ?: null,
        $humMinAmb   ?: null,
        $humMaxAmb   ?: null,
        $humOptAmb   ?: null,
        $humMinSol   ?: null,
        $humMaxSol   ?: null,
        $humOptSol   ?: null,
        $tempMin     ?: null,
        $tempMax     ?: null,
        $tempOpt     ?: null,
        $tempsPousse ?: null,
        $_SESSION['id']
    ]);
    $newId = $pdo->lastInsertId();

    echo json_encode([
        'success'  => true,
        'redirect' => 'content/culture.php?id=' . $newId,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'errors' => ['Erreur serveur.']]);
}