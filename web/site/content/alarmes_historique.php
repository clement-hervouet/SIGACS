<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<p>Non autorisé.</p>'; exit;
}
require_once __DIR__ . '/../config/config.php';

$pdo = get_pdo('app');

$par_page_autorise = [15, 30];
$par_page = filter_input(INPUT_GET, 'par_page', FILTER_VALIDATE_INT);
if (!in_array($par_page, $par_page_autorise)) $par_page = 15;

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if (!$page || $page < 1) $page = 1;

$categories = [
    'configuration' => ['BAC_NOT_FOUND', 'UNKNOWN_SENSOR', 'INVALID_PAYLOAD',
                        'INVALID_VALUE', 'VALUE_OUT_OF_RANGE', 'INVALID_ENCODING'],
    'technique'     => ['DB_ERROR', 'DB_INSERT_ERROR'],
    'systeme'       => ['MQTT_DISCONNECT', 'UNEXPECTED_ERROR'],
];

$filtre = filter_input(INPUT_GET, 'categorie', FILTER_SANITIZE_SPECIAL_CHARS);
if (!array_key_exists($filtre, $categories)) $filtre = '';

if ($filtre !== '') {
    $types        = $categories[$filtre];
    $placeholders = implode(',', array_fill(0, count($types), '?'));
    $whereClause  = "acquittee = 1 AND type_erreur IN ($placeholders)";
    $whereParams  = $types;
} else {
    $whereClause = 'acquittee = 1';
    $whereParams = [];
}

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM error WHERE $whereClause");
$stmtCount->execute($whereParams);
$total = (int)$stmtCount->fetchColumn();

$nb_pages = max(1, (int)ceil($total / $par_page));
if ($page > $nb_pages) $page = $nb_pages;
$offset = ($page - 1) * $par_page;

$stmt = $pdo->prepare("
    SELECT id_error, type_erreur, message, valeur, erreur_a
    FROM error
    WHERE $whereClause
    ORDER BY erreur_a DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($whereParams, [$par_page, $offset]));
$historique = $stmt->fetchAll();

function categorieErreur(string $type): string {
    $conf = ['BAC_NOT_FOUND', 'UNKNOWN_SENSOR', 'INVALID_PAYLOAD',
             'INVALID_VALUE', 'VALUE_OUT_OF_RANGE', 'INVALID_ENCODING'];
    $tech = ['DB_ERROR', 'DB_INSERT_ERROR'];
    $sys  = ['MQTT_DISCONNECT', 'UNEXPECTED_ERROR'];
    if (in_array($type, $conf)) return 'Configuration';
    if (in_array($type, $tech)) return 'Technique';
    if (in_array($type, $sys))  return 'Système';
    return 'Inconnue';
}

$urlBase   = 'content/alarmes_historique.php?par_page=' . $par_page . ($filtre ? '&categorie=' . $filtre : '');
$urlRetour = 'content/alarmes.php?par_page=' . $par_page . ($filtre ? '&categorie=' . $filtre : '');
?>

<section class="detail-serre">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <h2 style="margin:0;">Historique des alarmes</h2>
        <span style="cursor:pointer; text-decoration:underline;"
              data-action="content"
              data-target="<?= htmlspecialchars($urlRetour) ?>">
            ← Alarmes en cours
        </span>
    </div>

    <!-- Filtre par catégorie -->
    <div style="margin-bottom:12px;">
        <label>Filtrer par catégorie :
            <select id="selectFiltreHist">
                <option value="" <?= $filtre === '' ? 'selected' : '' ?>>Toutes</option>
                <option value="configuration" <?= $filtre === 'configuration' ? 'selected' : '' ?>>Configuration</option>
                <option value="technique"     <?= $filtre === 'technique'     ? 'selected' : '' ?>>Technique</option>
                <option value="systeme"       <?= $filtre === 'systeme'       ? 'selected' : '' ?>>Système</option>
            </select>
        </label>
    </div>

    <?php if ($total === 0): ?>
        <p>Aucune alarme dans l'historique<?= $filtre ? ' pour cette catégorie' : '' ?>.</p>
    <?php else: ?>

    <table class="detail-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Catégorie</th>
                <th>Type</th>
                <th>Message</th>
                <th>Valeur reçue</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($historique as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['erreur_a']) ?></td>
                <td><?= htmlspecialchars(categorieErreur($a['type_erreur'])) ?></td>
                <td><?= htmlspecialchars($a['type_erreur']) ?></td>
                <td><?= htmlspecialchars($a['message']) ?></td>
                <td><?= htmlspecialchars($a['valeur'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div style="display:flex; align-items:center; gap:16px; margin-top:12px;">
        <span>Lignes par page :
            <select id="selectParPageHist">
                <?php foreach ($par_page_autorise as $v): ?>
                <option value="<?= $v ?>" <?= $v === $par_page ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </span>
        <span>
            <?php if ($page > 1): ?>
                <span style="cursor:pointer; text-decoration:underline;"
                      data-action="content"
                      data-target="<?= htmlspecialchars($urlBase) ?>&page=<?= $page - 1 ?>">
                    ← Précédent
                </span>
            <?php endif; ?>
            &nbsp;Page <?= $page ?> / <?= $nb_pages ?>&nbsp;
            <?php if ($page < $nb_pages): ?>
                <span style="cursor:pointer; text-decoration:underline;"
                      data-action="content"
                      data-target="<?= htmlspecialchars($urlBase) ?>&page=<?= $page + 1 ?>">
                    Suivant →
                </span>
            <?php endif; ?>
        </span>
    </div>

    <?php endif; ?>
</section>

<script>
(function () {
    const parPageSelect = document.getElementById('selectParPageHist');
    const filtreSelect  = document.getElementById('selectFiltreHist');
    const par_page      = <?= $par_page ?>;
    const filtre        = <?= json_encode($filtre) ?>;

    function urlHist(page, pp, cat) {
        let url = 'content/alarmes_historique.php?page=' + page + '&par_page=' + pp;
        if (cat) url += '&categorie=' + encodeURIComponent(cat);
        return url;
    }

    if (parPageSelect) {
        parPageSelect.addEventListener('change', () => {
            if (typeof loadContent === 'function')
                loadContent(urlHist(1, parPageSelect.value, filtre));
        });
    }

    if (filtreSelect) {
        filtreSelect.addEventListener('change', () => {
            if (typeof loadContent === 'function')
                loadContent(urlHist(1, par_page, filtreSelect.value));
        });
    }
})();
</script>