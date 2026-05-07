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
$stmt->execute([$id, $_SESSION['id']]);
$c = $stmt->fetch();

if (!$c) { echo '<p>Culture introuvable.</p>'; exit; }

// Helper: display float or dash
$val = fn($v, $unit = '') => $v !== null ? $v . ($unit ? ' ' . $unit : '') : '—';
?>

<section class="detail-culture">

    <!-- ── IDENTITY ── -->
    <div class="culture-header">
        <?php if ($c['icone']): ?>
            <img src="<?= htmlspecialchars($c['icone']) ?>"
                 alt="<?= htmlspecialchars($c['plante']) ?>"
                 class="culture-icon">
        <?php endif; ?>
        <div>
            <h2><?= htmlspecialchars($c['plante']) ?></h2>
            <?php if ($c['plante_latin']): ?>
                <p class="latin"><em><?= htmlspecialchars($c['plante_latin']) ?></em></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── GROWTH ── -->
    <h3>Croissance</h3>
    <table class="detail-table">
        <tr>
            <th>Temps de pousse</th>
            <td><?= $val($c['tempsPousse'], 'jours') ?></td>
        </tr>
    </table>

    <!-- ── TEMPERATURE ── -->
    <h3>Température ambiante</h3>
    <table class="detail-table">
        <thead>
            <tr><th>Minimum</th><th>Optimal</th><th>Maximum</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><?= $val($c['tempMin'], '°C') ?></td>
                <td><?= $val($c['tempOpt'], '°C') ?></td>
                <td><?= $val($c['tempMax'], '°C') ?></td>
            </tr>
        </tbody>
    </table>

    <!-- ── AMBIENT HUMIDITY ── -->
    <h3>Humidité ambiante</h3>
    <table class="detail-table">
        <thead>
            <tr><th>Minimum</th><th>Optimal</th><th>Maximum</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><?= $val($c['humMinAmb'], '%') ?></td>
                <td><?= $val($c['humOptAmb'], '%') ?></td>
                <td><?= $val($c['humMaxAmb'], '%') ?></td>
            </tr>
        </tbody>
    </table>

    <!-- ── SOIL HUMIDITY ── -->
    <h3>Humidité du sol</h3>
    <table class="detail-table">
        <thead>
            <tr><th>Minimum</th><th>Optimal</th><th>Maximum</th></tr>
        </thead>
        <tbody>
            <tr>
                <td><?= $val($c['humMinSol'], '%') ?></td>
                <td><?= $val($c['humOptSol'], '%') ?></td>
                <td><?= $val($c['humMaxSol'], '%') ?></td>
            </tr>
        </tbody>
    </table>

</section>

<style>
.culture-header {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}

.culture-icon {
    width: 80px;
    height: 80px;
    object-fit: contain;
    border-radius: 8px;
    border: 1px solid #ddd;
    padding: 4px;
    background: white;
}

.latin {
    color: #666;
    margin: 0;
}

.detail-table {
    border-collapse: collapse;
    margin-bottom: 20px;
    width: 100%;
}

.detail-table th,
.detail-table td {
    border: 1px solid #ccc;
    padding: 8px 14px;
    text-align: center;
}

.detail-table thead th {
    background: #f0f0f0;
    font-weight: bold;
}
</style>