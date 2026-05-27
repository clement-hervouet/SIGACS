<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<p>Non autorisé.</p>'; exit;
}
require_once __DIR__ . '/../config/config.php';

$pdo = get_pdo('app');

$par_page_autorise = [15, 30,60,120,240,480];
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
if ($filtre === null || !array_key_exists($filtre, $categories)) $filtre = '';

// Construction de la clause WHERE selon le filtre
if ($filtre !== '') {
    $types       = $categories[$filtre];
    $placeholders = implode(',', array_fill(0, count($types), '?'));
    $whereClause = "acquittee = 0 AND type_erreur IN ($placeholders)";
    $whereParams = $types;
} else {
    $whereClause = 'acquittee = 0';
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
$alarmes = $stmt->fetchAll();

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

$urlBase = 'content/alarmes.php?par_page=' . $par_page . ($filtre ? '&categorie=' . $filtre : '');
$urlHist = 'content/erreurs_historique.php?par_page=' . $par_page . ($filtre ? '&categorie=' . $filtre : '');?>

<section class="detail-serre">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <h2 style="margin:0;">Alarmes en cours</h2>
        <span style="cursor:pointer; text-decoration:underline;"
              data-action="content"
              data-target="<?= htmlspecialchars($urlHist) ?>">
            Voir l'historique →
        </span>
    </div>

    <!-- Filtre par catégorie -->
    <div style="margin-bottom:12px;">
        <label>Filtrer par catégorie :
            <select id="selectFiltre">
                <option value="" <?= $filtre === '' ? 'selected' : '' ?>>Toutes</option>
                <option value="configuration" <?= $filtre === 'configuration' ? 'selected' : '' ?>>Configuration</option>
                <option value="technique"     <?= $filtre === 'technique'     ? 'selected' : '' ?>>Technique</option>
                <option value="systeme"       <?= $filtre === 'systeme'       ? 'selected' : '' ?>>Système</option>
            </select>
        </label>
    </div>

    <?php if ($total === 0): ?>
        <p>Aucune alarme active<?= $filtre ? ' pour cette catégorie' : '' ?>.</p>
    <?php else: ?>

    <div style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
        <button id="btnAcquitterSelection" disabled>Acquitter la sélection</button>
        <span id="compteurSelection">0 sélectionnée(s)</span>
    </div>

    <table class="detail-table" id="tableAlarmes">
        <thead>
            <tr>
                <th><input type="checkbox" id="toutSelectionner" title="Tout sélectionner"></th>
                <th>Date</th>
                <th>Catégorie</th>
                <th>Type</th>
                <th>Message</th>
                <th>Valeur reçue</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($alarmes as $a):
            $cat = categorieErreur($a['type_erreur']);
        ?>
            <tr id="alarme-<?= (int)$a['id_error'] ?>" class="ligne-alarme" style="cursor:pointer;">
                <td><input type="checkbox" class="chk-alarme" value="<?= (int)$a['id_error'] ?>"></td>
                <td><?= htmlspecialchars($a['erreur_a']) ?></td>
                <td><?= htmlspecialchars($cat) ?></td>
                <td><?= htmlspecialchars($a['type_erreur']) ?></td>
                <td><?= htmlspecialchars($a['message']) ?></td>
                <td><?= htmlspecialchars($a['valeur'] ?? '—') ?></td>
                <td>
                    <button class="btn-acquitter" data-id="<?= (int)$a['id_error'] ?>">Acquitter</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div style="display:flex; align-items:center; gap:16px; margin-top:12px;">
        <span>Lignes par page :
            <select id="selectParPage">
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
    const btnAcquitter  = document.getElementById('btnAcquitterSelection');
    const toutChk       = document.getElementById('toutSelectionner');
    const compteur      = document.getElementById('compteurSelection');
    const parPageSelect = document.getElementById('selectParPage');
    const filtreSelect  = document.getElementById('selectFiltre');
    const par_page      = <?= $par_page ?>;
    const filtre        = <?= json_encode($filtre) ?>;

    function getCoches() {
        return [...document.querySelectorAll('.chk-alarme:checked')];
    }

    function majBouton() {
        const n = getCoches().length;
        if (btnAcquitter) btnAcquitter.disabled = n === 0;
        if (compteur)     compteur.textContent  = n + ' sélectionnée(s)';
    }

    function urlAlarmes(page, pp, cat) {
        let url = 'content/erreurs.php?page=' + page + '&par_page=' + pp;
        if (cat) url += '&categorie=' + encodeURIComponent(cat);
        return url;
    }

    // Clic sur ligne → coche la checkbox
    document.querySelectorAll('.ligne-alarme').forEach(tr => {
        tr.addEventListener('click', (e) => {
            if (e.target.type === 'checkbox' || e.target.tagName === 'BUTTON') return;
            const chk = tr.querySelector('.chk-alarme');
            if (chk) {
                chk.checked = !chk.checked;
                if (toutChk) toutChk.checked = document.querySelectorAll('.chk-alarme:not(:checked)').length === 0;
                majBouton();
            }
        });
    });

    if (toutChk) {
        toutChk.addEventListener('change', () => {
            document.querySelectorAll('.chk-alarme').forEach(c => c.checked = toutChk.checked);
            majBouton();
        });
    }

    document.querySelectorAll('.chk-alarme').forEach(c => {
        c.addEventListener('change', () => {
            if (toutChk) toutChk.checked = document.querySelectorAll('.chk-alarme:not(:checked)').length === 0;
            majBouton();
        });
    });

    // Acquittement unitaire
    document.querySelectorAll('.btn-acquitter').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const id  = btn.dataset.id;
            const res = await fetch('/content/erreur_acquitter.php', {
                method: 'POST',
                body: new URLSearchParams({ id }),
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('alarme-' + id).remove();
                verifierTableauVide();
                majBouton();
            } else {
                alert(data.errors.join('\n'));
            }
        });
    });

    // Acquittement en lot
    if (btnAcquitter) {
        btnAcquitter.addEventListener('click', async () => {
            const ids = getCoches().map(c => parseInt(c.value));
            const res = await fetch('/content/erreur_acquitter.php', {
                method: 'POST',
                body: new URLSearchParams({ ids: JSON.stringify(ids) }),
            });
            const data = await res.json();
            if (data.success) {
                ids.forEach(id => {
                    const tr = document.getElementById('alarme-' + id);
                    if (tr) tr.remove();
                });
                verifierTableauVide();
                majBouton();
            } else {
                alert(data.errors.join('\n'));
            }
        });
    }

    function verifierTableauVide() {
        const tbody = document.querySelector('#tableAlarmes tbody');
        if (tbody && tbody.children.length === 0) {
            document.querySelector('.detail-serre').innerHTML =
                '<h2>Alarmes en cours</h2><p>Aucune alarme active.</p>';
        }
    }

    // Changement lignes par page
    if (parPageSelect) {
        parPageSelect.addEventListener('change', () => {
            if (typeof loadContent === 'function')
                loadContent(urlAlarmes(1, parPageSelect.value, filtre));
        });
    }

    // Changement filtre catégorie
    if (filtreSelect) {
        filtreSelect.addEventListener('change', () => {
            if (typeof loadContent === 'function')
                loadContent(urlAlarmes(1, par_page, filtreSelect.value));
        });
    }
})();
</script>