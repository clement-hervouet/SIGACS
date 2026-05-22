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

$bacId     = filter_input(INPUT_POST, 'bac',       FILTER_VALIDATE_INT);
$direction = trim($_POST['direction'] ?? ''); // 'row' or 'col'
$action    = trim($_POST['action']    ?? ''); // 'add' or 'remove'

if (!$bacId || !in_array($direction, ['row', 'col']) || !in_array($action, ['add', 'remove'])) {
    echo json_encode(['success' => false, 'errors' => ['Paramètres invalides.']]);
    exit;
}

try {
    $pdo = get_pdo('app');

    // Verify ownership
    $check = $pdo->prepare('
        SELECT b.id_bac, b.x_taille, b.y_taille
        FROM bac b
        JOIN serre s ON s.id_serre = b.serre
        WHERE b.id_bac = ? AND s.cree_par = ?
    ');
    $check->execute([$bacId, $_SESSION['id']]);
    $bac = $check->fetch();

    if (!$bac) {
        echo json_encode(['success' => false, 'errors' => ['Bac introuvable.']]);
        exit;
    }

    $x = (int)$bac['x_taille'];
    $y = (int)$bac['y_taille'];

    if ($action === 'add') {

        // Simply increment the dimension
        if ($direction === 'col') {
            $pdo->prepare('UPDATE bac SET x_taille = x_taille + 1 WHERE id_bac = ?')
                ->execute([$bacId]);
        } else {
            $pdo->prepare('UPDATE bac SET y_taille = y_taille + 1 WHERE id_bac = ?')
                ->execute([$bacId]);
        }
        echo json_encode(['success' => true]);

    } else {
        // action = remove — check minimum size
        if ($direction === 'col' && $x <= 1) {
            echo json_encode(['success' => false, 'errors' => ['Le bac doit avoir au moins 1 colonne.']]);
            exit;
        }
        if ($direction === 'row' && $y <= 1) {
            echo json_encode(['success' => false, 'errors' => ['Le bac doit avoir au moins 1 rangée.']]);
            exit;
        }

        // Check if last col/row has blocs
        if ($direction === 'col') {
            $lastIndex = $x - 1; // 0-indexed
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM bloc WHERE bac = ? AND x = ?');
            $stmt->execute([$bacId, $lastIndex]);
        } else {
            $lastIndex = $y - 1;
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM bloc WHERE bac = ? AND y = ?');
            $stmt->execute([$bacId, $lastIndex]);
        }

        $count = (int)$stmt->fetchColumn();

        // If blocs exist and client didn't confirm yet — return warning
        $confirmed = filter_input(INPUT_POST, 'confirmed', FILTER_VALIDATE_BOOLEAN);
        if ($count > 0 && !$confirmed) {
            echo json_encode([
                'success'  => false,
                'confirm'  => true,
                'message'  => $count . ' culture(s) seront supprimées. Confirmer ?',
            ]);
            exit;
        }

        // Delete blocs in last col/row
        if ($direction === 'col') {
            $pdo->prepare('DELETE FROM bloc WHERE bac = ? AND x = ?')
                ->execute([$bacId, $lastIndex]);
            $pdo->prepare('UPDATE bac SET x_taille = x_taille - 1 WHERE id_bac = ?')
                ->execute([$bacId]);
        } else {
            $pdo->prepare('DELETE FROM bloc WHERE bac = ? AND y = ?')
                ->execute([$bacId, $lastIndex]);
            $pdo->prepare('UPDATE bac SET y_taille = y_taille - 1 WHERE id_bac = ?')
                ->execute([$bacId]);
        }

        echo json_encode(['success' => true]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
}