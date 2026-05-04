<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<p>Non autorisé.</p>'; exit;
}
require_once __DIR__ . '/../config/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { echo '<p>Bac introuvable.</p>'; exit; }

$pdo = get_pdo('app');

// Fetch bac — verify ownership via serre→cree_par
$stmt = $pdo->prepare('
    SELECT b.*, s.nom AS serre_nom, s.id_serre
    FROM bac b
    JOIN serre s ON s.id_serre = b.serre
    WHERE b.id_bac = ? AND s.cree_par = ?
');
$stmt->execute([$id, $_SESSION['id']]);
$bac = $stmt->fetch();

if (!$bac) { echo '<p>Bac introuvable.</p>'; exit; }

// Fetch blocs with their culture
$stmtBlocs = $pdo->prepare('
    SELECT bl.x, bl.y, bl.vide, bl.plante_a,
           c.plante, c.id_culture
    FROM bloc bl
    LEFT JOIN culture c ON c.id_culture = bl.culture
    WHERE bl.bac = ?
    ORDER BY bl.y ASC, bl.x ASC
');
$stmtBlocs->execute([$id]);
$blocs = $stmtBlocs->fetchAll();

// Index by [y][x] for grid rendering
$grid = [];
foreach ($blocs as $bloc) {
    $grid[$bloc['y']][$bloc['x']] = $bloc;
}
?>

<section class="detail-bac">
    <h2>Bac N°<?= (int)$bac['numero'] ?></h2>
    <p class="breadcrumb">
        ← <span class="content-link"
                data-action="content"
                data-target="content/serre.php?id=<?= (int)$bac['id_serre'] ?>">
            <?= htmlspecialchars($bac['serre_nom']) ?>
        </span>
    </p>

    <table class="detail-table">
        <tr>
            <th>Taille</th>
            <td><?= (int)$bac['x_taille'] ?> × <?= (int)$bac['y_taille'] ?> blocs</td>
        </tr>
        <tr>
            <th>Arrosage</th>
            <td><?= $bac['arrose'] ? '💧 Activé' : 'Désactivé' ?></td>
        </tr>
    </table>

    <h3>Grille des blocs</h3>

    <?php if (empty($blocs)): ?>
        <p>Aucun bloc enregistré pour ce bac.</p>
    <?php else: ?>
    <div class="bloc-grid"
         style="grid-template-columns: repeat(<?= (int)$bac['x_taille'] ?>, 3rem);">
        <?php for ($y = 0; $y < $bac['y_taille']; $y++):
              for ($x = 0; $x < $bac['x_taille']; $x++):
                  $b    = $grid[$y][$x] ?? null;
                  $vide = !$b || $b['vide'];
                  $nom  = $b['plante'] ?? null;
        ?>
        <div class="bloc <?= $vide ? 'bloc-vide' : 'bloc-plante' ?>"
             title="<?= $nom ? htmlspecialchars($nom) : 'Vide' ?>">
            <?php if ($nom): ?>
            <span class="content-link"
                  data-action="content"
                  data-target="content/culture.php?id=<?= (int)$b['id_culture'] ?>">
                <?= htmlspecialchars(mb_substr($nom, 0, 3)) ?>
            </span>
            <?php endif; ?>
        </div>
        <?php endfor; endfor; ?>
    </div>
    <?php endif; ?>
</section>