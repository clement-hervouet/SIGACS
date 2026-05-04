<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<p>Non autorisé.</p>'; exit;
}
require_once __DIR__ . '/../config/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { echo '<p>Culture introuvable.</p>'; exit; }

$pdo = get_pdo('app');

$stmt = $pdo->prepare('
    SELECT * FROM culture
    WHERE id_culture = ? AND cree_par = ?
');
$stmt->execute([$id, $_SESSION['username']]);
$c = $stmt->fetch();

if (!$c) { echo '<p>Culture introuvable.</p>'; exit; }

// Helper: display float or dash
$val = fn($v, $unit = '') => $v !== null ? $v . ($unit ? ' ' . $unit : '') : '—';
?>

<section class="detail-culture">
    <h2><?= htmlspecialchars($c['plante']) ?></h2>
    <?php if ($c['plante_latin']): ?>
        <p><em><?= htmlspecialchars($c['plante_latin']) ?></em></p>
    <?php endif; ?>

    <h3>Humidité ambiante</h3>
    <table class="detail-table">
        <tr><th>Minimum</th><td><?= $val($c['humMinAmb'], '%') ?></td></tr>
        <tr><th>Maximum</th><td><?= $val($c['humMaxAmb'], '%') ?></td></tr>
        <tr><th>Optimal</th><td><?= $val($c['humOptAmb'], '%') ?></td></tr>
    </table>

    <h3>Humidité du sol</h3>
    <table class="detail-table">
        <tr><th>Minimum</th><td><?= $val($c['humMinSol'], '%') ?></td></tr>
        <tr><th>Maximum</th><td><?= $val($c['humMaxSol'], '%') ?></td></tr>
        <tr><th>Optimal</th><td><?= $val($c['humOptSol'], '%') ?></td></tr>
    </table>

    <h3>Température ambiante</h3>
    <table class="detail-table">
        <tr><th>Minimum</th><td><?= $val($c['tempMin'], '°C') ?></td></tr>
        <tr><th>Maximum</th><td><?= $val($c['tempMax'], '°C') ?></td></tr>
        <tr><th>Optimal</th><td><?= $val($c['tempOpt'], '°C') ?></td></tr>
    </table>

    <h3>Pousse</h3>
    <table class="detail-table">
        <tr><th>Temps usuel</th><td><?= $val($c['tempsPousse'], 'jours') ?></td></tr>
    </table>
</section>