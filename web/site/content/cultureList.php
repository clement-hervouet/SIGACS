<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<p>Non autorisé.</p>'; exit;
}
require_once __DIR__ . '/../config/config.php';

$pdo = get_pdo('app');

$stmt = $pdo->prepare('
    SELECT id_culture, plante, plante_latin, tempsPousse
    FROM culture
    WHERE cree_par = ?
    ORDER BY plante ASC
');
$stmt->execute([$_SESSION['id']]);
$cultures = $stmt->fetchAll();
?>

<section class="detail-culture-list">
    <h2>Cultures disponibles</h2>

    <?php if (empty($cultures)): ?>
        <p>Aucune culture enregistrée.</p>
    <?php else: ?>
    <ul class="culture-list">
        <?php foreach ($cultures as $c): ?>
        <li>
            <div class="line"
                 data-action="content"
                 data-target="content/culture.php?id=<?= (int)$c['id_culture'] ?>">
                <img src="assets/static/icons/navigation_tree/potted-plant-icon.png" alt="">
                <a>
                    <?= htmlspecialchars($c['plante']) ?>
                    <?php if ($c['plante_latin']): ?>
                        <em>— <?= htmlspecialchars($c['plante_latin']) ?></em>
                    <?php endif; ?>
                    <?php if ($c['tempsPousse']): ?>
                        — <?= (int)$c['tempsPousse'] ?> jours
                    <?php endif; ?>
                </a>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>