<?php
// content/serre.php — fragment injecté dans .content
session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(403); exit;
}
require_once '../config/config.php';

$id_serre = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_serre <= 0) { echo '<p class="no-data">// serre introuvable</p>'; exit; }

$pdo = get_pdo("app");
$stmt = $pdo->prepare('
    SELECT s.*, c.type AS ctrl_type, c.ip AS ctrl_ip, c.status AS ctrl_status
    FROM serre s
    LEFT JOIN controleur c ON c.id_controleur = s.controleur
    WHERE s.id_serre = ?
');
$stmt->execute([$id_serre]);
$serre = $stmt->fetch();
if (!$serre) { echo '<p class="no-data">// serre introuvable</p>'; exit; }

$mesures_stmt = $pdo->prepare('
    SELECT cap.type, cap.unite, cap.valeurMinCapteur, cap.valeurMaxCapteur,
           m.value, m.mesure_a
    FROM capteur cap
    JOIN (
        SELECT m2.capteur, MAX(m2.mesure_a) AS max_date
        FROM mesure m2
        JOIN bac b2 ON b2.id_bac = m2.bac
        WHERE b2.serre = ?
        GROUP BY m2.capteur
    ) latest ON latest.capteur = cap.id_capteur
    JOIN mesure m ON m.capteur = latest.capteur AND m.mesure_a = latest.max_date
    JOIN bac b ON b.id_bac = m.bac
    WHERE b.serre = ?
');
$mesures_stmt->execute([$id_serre, $id_serre]);

$mesures = $mesures_stmt->fetchAll();

$capteur_colors = [
    'humiditeAmbiante'    => '#00922A',
    'humiditeSol'         => '#00B0F0',
    'temperatureAmbiante' => '#EF9F27',
];
$capteur_labels = [
    'humiditeAmbiante'    => 'Humidité ambiante',
    'humiditeSol'         => 'Humidité sol',
    'temperatureAmbiante' => 'Température ambiante',
];
?>

<div class="serre-page">

    <div class="serre-header">
        <div class="serre-header-left">
            <div class="serre-icon">
                <img src="assets/static/icons/map-pin-house.svg" alt="">
            </div>
            <div>
                <div class="serre-title"><?php echo htmlspecialchars($serre['nom']); ?></div>
                <div class="serre-subtitle">Serre N°<?php echo $serre['numero']; ?></div>
            </div>
            <?php if ($serre['ctrl_status']): ?>
                <span class="status-badge status-online">● en ligne</span>
            <?php else: ?>
                <span class="status-badge status-offline">○ hors ligne</span>
            <?php endif; ?>
        </div>
        <div class="serre-actions">
            <button class="action-btn" onclick="openModal('forms/edit/edit_serre.html')">✎ Modifier</button>
            <button class="action-btn danger">✕ Supprimer</button>
        </div>
    </div>

    <p class="section-label">Informations générales</p>
    <div class="info-grid">
        <div class="info-card">
            <div class="info-card-label">↟ Localisation</div>
            <div class="info-card-value" style="font-size:.9rem;line-height:1.4">
                <?php echo $serre['localisation'] ? htmlspecialchars($serre['localisation']) : '—'; ?>
            </div>
        </div>
        <div class="info-card">
            <div class="info-card-label">⊡ Surface</div>
            <div class="info-card-value"><?php echo $serre['surface']; ?> <span class="unit">m²</span></div>
        </div>
        <div class="info-card">
            <div class="info-card-label">⊞ Bacs</div>
            <div class="info-card-value"><?php echo $serre['nbBac']; ?> <span class="unit">bacs</span></div>
        </div>
        <div class="info-card">
            <div class="info-card-label">⚙ Contrôleur</div>
            <div class="info-card-value" style="font-family:var(--font-mono);font-size:.9rem">
                <?php echo $serre['ctrl_type'] ? htmlspecialchars($serre['ctrl_type']) : '—'; ?>
                <?php if ($serre['ctrl_ip']): ?>
                    <div style="font-size:.65rem;color:var(--text-muted);margin-top:2px">
                        <?php echo htmlspecialchars($serre['ctrl_ip']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <hr class="section-divider">

    <p class="section-label">Capteurs — dernière mesure</p>

    <?php if (empty($mesures)): ?>
        <p class="no-data">// aucune mesure disponible pour cette serre</p>
    <?php else: ?>
        <div class="sensor-grid">
            <?php foreach ($mesures as $m):
                $type  = $m['type'];
                $color = $capteur_colors[$type] ?? '#8b949e';
                $label = $capteur_labels[$type] ?? $type;
                $min   = $m['valeurMinCapteur'];
                $max   = $m['valeurMaxCapteur'];
                $val   = round($m['value'], 1);
                $pct   = $max > $min ? round(($val - $min) / ($max - $min) * 100) : 0;
                $pct   = max(0, min(100, $pct));
                $date  = (new DateTime($m['mesure_a']))->format('d/m/Y H:i');
            ?>
            <div class="sensor-card">
                <div class="sensor-top">
                    <span class="sensor-name"><?php echo htmlspecialchars($label); ?></span>
                    <span class="sensor-dot" style="background:<?php echo $color; ?>"></span>
                </div>
                <div class="sensor-value">
                    <?php echo $val; ?>
                    <span class="sensor-unit"><?php echo htmlspecialchars($m['unite']); ?></span>
                </div>
                <div class="sensor-bar-wrap">
                    <div class="sensor-bar" style="width:<?php echo $pct; ?>%;background:<?php echo $color; ?>"></div>
                </div>
                <div class="sensor-range">
                    <span><?php echo $min; ?><?php echo $m['unite']; ?></span>
                    <span><?php echo $max; ?><?php echo $m['unite']; ?></span>
                </div>
                <div class="sensor-time">↻ <?php echo $date; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>