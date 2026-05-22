<?php
// This file is included by dashboard.php which already started the session
// and required config.php — $pdo is available via get_pdo()

$pdo = get_pdo('app');
$username = $_SESSION['username'];

// Single query: all serres + their bacs for this user
$stmt = $pdo->prepare('
    SELECT
        s.id_serre,
        s.nom        AS serre_nom,
        s.numero     AS serre_numero,
        b.id_bac,
        b.numero     AS bac_numero
    FROM serre s
    LEFT JOIN bac b ON b.serre = s.id_serre
    WHERE s.cree_par = ?
    ORDER BY s.numero ASC, b.numero ASC
');
$stmt->execute([$_SESSION['id']]);
$rows = $stmt->fetchAll();

// Group bacs under their serre
$serres = [];
foreach ($rows as $row) {
    $sid = $row['id_serre'];
    if (!isset($serres[$sid])) {
        $serres[$sid] = [
            'id'     => $sid,
            'nom'    => $row['serre_nom'],
            'numero' => $row['serre_numero'],
            'bacs'   => [],
        ];
    }
    if ($row['id_bac'] !== null) {
        $serres[$sid]['bacs'][] = [
            'id'     => $row['id_bac'],
            'numero' => $row['bac_numero'],
        ];
    }
}

// All cultures for this user
$stmt2 = $pdo->prepare('
    SELECT id_culture, plante
    FROM culture
    WHERE cree_par = ?
    ORDER BY plante ASC
');
$stmt2->execute([$_SESSION['id']]);
$cultures = $stmt2->fetchAll();
?>

<ul>

    <!-- ── NEW SERRE ── -->
    <li>
        <div class="line"
             data-action="modal"
             data-target="forms/new/new_serre.html">
            <img src="assets/static/icons/navigation_tree/house-plus.svg" alt="">
            <a>Ajouter une nouvelle serre</a>
        </div>
    </li>

    <!-- ── ONE LI PER SERRE ── -->
    <?php foreach ($serres as $serre): ?>
    <li>
        <div class="line"
             data-action="content"
             data-target="content/serre.php?id=<?= (int)$serre['id'] ?>">
            <img src="assets/static/icons/navigation_tree/square-plus.svg" alt="">
            <img src="assets/static/icons/navigation_tree/greenhouse.png" alt="">
            <a><?= htmlspecialchars($serre['nom'] ?: 'Serre N°' . $serre['numero']) ?></a>
        </div>

        <ul>
            <!-- new bac button: carries serre id for the modal -->
            <li>
                <div class="line"
                     data-action="modal"
                     data-target="forms/new/new_bac.html"
                     data-serre-id="<?= (int)$serre['id'] ?>">
                    <img src="assets/static/icons/navigation_tree/grid-2x2-plus.svg" alt="">
                    <a>Ajouter un nouveau bac</a>
                </div>
            </li>

            <!-- one li per bac -->
            <?php foreach ($serre['bacs'] as $bac): ?>
            <li>
                <div class="line"
                     data-action="content"
                     data-target="content/bac.php?id=<?= (int)$bac['id'] ?>">
                    <img src="assets/static/icons/navigation_tree/planter-box.png" alt="">
                    <a>Bac N°<?= (int)$bac['numero'] ?></a>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </li>
    <?php endforeach; ?>

    <!-- ── NEW CULTURE ── -->
    <li>
        <div class="line"
             data-action="modal"
             data-target="forms/new/new_culture.html">
            <img src="assets/static/icons/navigation_tree/plant-plus.svg" alt="">
            <a>Ajouter une nouvelle culture</a>
        </div>
    </li>

    <!-- ── CULTURE LIST (expandable) ── -->
    <li>
        <div class="line"
             data-action="content"
             data-target="content/cultureList.php">
            <img src="assets/static/icons/navigation_tree/square-plus.svg" alt="">
            <img src="assets/static/icons/navigation_tree/potted-plant-icon.png" alt="">
            <a>Liste des cultures disponibles</a>
        </div>

        <ul>
            <?php foreach ($cultures as $culture): ?>
            <li>
                <div class="line"
                     data-action="content"
                     data-target="content/culture.php?id=<?= (int)$culture['id_culture'] ?>">
                    <img src="assets/static/icons/navigation_tree/potted-plant-icon.png" alt="">
                    <a><?= htmlspecialchars($culture['plante']) ?></a>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </li>

</ul>