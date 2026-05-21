<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<p>Non autorisé.</p>'; exit;
}
require_once __DIR__ . '/../config/config.php';

$pdo = get_pdo('app');

$par_page_autorise = [15, 30,60,120,240,480,960];
$par_page = filter_input(INPUT_GET, 'par_page', FILTER_VALIDATE_INT);
if (!in_array($par_page, $par_page_autorise)) $par_page = 15;

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
if (!$page || $page < 1) $page = 1;

$total = (int)$pdo->query('SELECT COUNT(*) FROM error WHERE acquittee = 1')->fetchColumn();
$nb_pages = max(1, (int)ceil($total / $par_page));
if ($page > $nb_pages) $page = $nb_pages;
$offset = ($page - 1) * $par_page;

$stmt = $pdo->prepare('
    SELECT id_error, type_erreur, message, valeur, erreur_a
    FROM error
    WHERE acquittee = 1
    ORDER BY erreur_a DESC
    LIMIT ? OFFSET ?
');
$stmt->execute([$par_page, $offset]);
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
?>

<section class="detail-serre">
    <h2>Historique des alarmes</h2>

    <?php if ($total === 0): ?>
        <p>Aucune alarme dans l'historique.</p>
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
                      data-target="content/alarmes_historique.php?page=<?= $page - 1 ?>&par_page=<?= $par_page ?>">
                    ← Précédent
                </span>
            <?php endif; ?>
            &nbsp;Page <?= $page ?> / <?= $nb_pages ?>&nbsp;
            <?php if ($page < $nb_pages): ?>
                <span style="cursor:pointer; text-decoration:underline;"
                      data-action="content"
                      data-target="content/alarmes_historique.php?page=<?= $page + 1 ?>&par_page=<?= $par_page ?>">
                    Suivant →
                </span>
            <?php endif; ?>
        </span>
    </div>

    <?php endif; ?>
</section>

<script>
(function () {
    const sel = document.getElementById('selectParPageHist');
    if (sel) {
        sel.addEventListener('change', () => {
            if (typeof loadContent === 'function') {
                loadContent('content/alarmes_historique.php?page=1&par_page=' + sel.value);
            }
        });
    }
})();
</script>