<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<p>Non autorisé.</p>'; exit;
}
require_once __DIR__ . '/../config/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { echo '<p>Serre introuvable.</p>'; exit; }

$pdo = get_pdo('app');

// Fetch serre — must belong to current user
$stmt = $pdo->prepare('
    SELECT s.*,
           c.ip     AS ctrl_ip,
           c.type   AS ctrl_type,
           c.status AS ctrl_status
    FROM serre s
    LEFT JOIN controleur c ON c.id_controleur = s.controleur
    WHERE s.id_serre = ? AND s.cree_par = ?
');
$stmt->execute([$id, $_SESSION['username']]);
$serre = $stmt->fetch();

if (!$serre) { echo '<p>Serre introuvable.</p>'; exit; }

// Fetch its bacs
$stmtBacs = $pdo->prepare('
    SELECT id_bac, numero, x_taille, y_taille, arrose
    FROM bac
    WHERE serre = ?
    ORDER BY numero ASC
');
$stmtBacs->execute([$id]);
$bacs = $stmtBacs->fetchAll();
?>

<section class="detail-serre">
    <h2><?= htmlspecialchars($serre['nom']) ?></h2>

    <table class="detail-table">
        <tr>
            <th>Localisation</th>
            <td><?= htmlspecialchars($serre['localisation'] ?? '—') ?></td>
        </tr>
        <tr>
            <th>Surface</th>
            <td><?= $serre['surface'] ?> m²</td>
        </tr>
        <tr>
            <th>Nombre de bacs</th>
            <td><?= (int)$serre['nbBac'] ?></td>
        </tr>
        <?php if ($serre['ctrl_ip']): ?>
        <tr>
            <th>Contrôleur</th>
            <td>
                <?= htmlspecialchars($serre['ctrl_type']) ?>
                — <?= htmlspecialchars($serre['ctrl_ip']) ?>
                — <?= $serre['ctrl_status'] ? '🟢 En ligne' : '🔴 Hors ligne' ?>
            </td>
        </tr>
        <?php endif; ?>
    </table>

    <h3>Bacs</h3>
    <?php if (empty($bacs)): ?>
        <p>Aucun bac dans cette serre.</p>
    <?php else: ?>
    <ul class="bac-list">
        <?php foreach ($bacs as $bac): ?>
        <li>
            <div class="line"
                 data-action="content"
                 data-target="content/bac.php?id=<?= (int)$bac['id_bac'] ?>">
                <img src="assets/static/icons/navigation_tree/planter-box.png" alt="">
                <a>
                    Bac N°<?= (int)$bac['numero'] ?>
                    — <?= (int)$bac['x_taille'] ?>×<?= (int)$bac['y_taille'] ?> blocs
                    — <?= $bac['arrose'] ? '💧 Arrosé' : 'Non arrosé' ?>
                </a>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>