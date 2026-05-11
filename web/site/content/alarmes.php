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

$total = (int)$pdo->query('SELECT COUNT(*) FROM error WHERE acquittee = 0')->fetchColumn();
$nb_pages = max(1, (int)ceil($total / $par_page));
if ($page > $nb_pages) $page = $nb_pages;
$offset = ($page - 1) * $par_page;

$stmt = $pdo->prepare('
    SELECT id_error, type_erreur, message, valeur, erreur_a
    FROM error
    WHERE acquittee = 0
    ORDER BY erreur_a DESC
    LIMIT ? OFFSET ?
');
$stmt->execute([$par_page, $offset]);
$alarmes = $stmt->fetchAll();
?>

<section class="detail-serre">
    <h2>Alarmes en cours</h2>

    <?php if ($total === 0): ?>
        <p>Aucune alarme active.</p>
    <?php else: ?>

    <div style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
        <button id="btnAcquitterSelection" disabled>
            Acquitter la sélection
        </button>
        <span id="compteurSelection">0 sélectionnée(s)</span>
    </div>

    <table class="detail-table" id="tableAlarmes">
        <thead>
            <tr>
                <th><input type="checkbox" id="toutSelectionner" title="Tout sélectionner"></th>
                <th>Date</th>
                <th>Type</th>
                <th>Message</th>
                <th>Valeur reçue</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($alarmes as $a): ?>
            <tr id="alarme-<?= (int)$a['id_error'] ?>">
                <td><input type="checkbox" class="chk-alarme" value="<?= (int)$a['id_error'] ?>"></td>
                <td><?= htmlspecialchars($a['erreur_a']) ?></td>
                <td><?= htmlspecialchars($a['type_erreur']) ?></td>
                <td><?= htmlspecialchars($a['message']) ?></td>
                <td><?= htmlspecialchars($a['valeur'] ?? '—') ?></td>
                <td>
                    <button class="btn-acquitter" data-id="<?= (int)$a['id_error'] ?>">
                        Acquitter
                    </button>
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
                      data-target="content/alarmes.php?page=<?= $page - 1 ?>&par_page=<?= $par_page ?>">
                    ← Précédent
                </span>
            <?php endif; ?>

            &nbsp;Page <?= $page ?> / <?= $nb_pages ?>&nbsp;

            <?php if ($page < $nb_pages): ?>
                <span style="cursor:pointer; text-decoration:underline;"
                      data-action="content"
                      data-target="content/alarmes.php?page=<?= $page + 1 ?>&par_page=<?= $par_page ?>">
                    Suivant →
                </span>
            <?php endif; ?>
        </span>
    </div>

    <?php endif; ?>
</section>

<script>
(function () {
    const btnAcquitter   = document.getElementById('btnAcquitterSelection');
    const toutChk        = document.getElementById('toutSelectionner');
    const compteur       = document.getElementById('compteurSelection');
    const parPageSelect  = document.getElementById('selectParPage');
    const parPage        = <?= $par_page ?>;
    const page           = <?= $page ?>;

    function getCoches() {
        return [...document.querySelectorAll('.chk-alarme:checked')];
    }

    function majBouton() {
        const n = getCoches().length;
        btnAcquitter.disabled = n === 0;
        compteur.textContent  = n + ' sélectionnée(s)';
    }

    // Tout sélectionner / désélectionner
    if (toutChk) {
        toutChk.addEventListener('change', () => {
            document.querySelectorAll('.chk-alarme').forEach(c => c.checked = toutChk.checked);
            majBouton();
        });
    }

    document.querySelectorAll('.chk-alarme').forEach(c => {
        c.addEventListener('change', () => {
            toutChk.checked = document.querySelectorAll('.chk-alarme:not(:checked)').length === 0;
            majBouton();
        });
    });

    // Acquittement unitaire
    document.querySelectorAll('.btn-acquitter').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const res  = await fetch('/content/alarme_acquitter.php', {
                method: 'POST',
                body: new URLSearchParams({ id }),
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('alarme-' + id).remove();
                verifierTableauVide();
            } else {
                alert(data.errors.join('\n'));
            }
        });
    });

    // Acquittement en lot
    btnAcquitter.addEventListener('click', async () => {
        const ids = getCoches().map(c => parseInt(c.value));
        const res  = await fetch('/content/alarme_acquitter.php', {
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

    function verifierTableauVide() {
        const tbody = document.querySelector('#tableAlarmes tbody');
        if (tbody && tbody.children.length === 0) {
            document.querySelector('.detail-serre').innerHTML =
                '<h2>Alarmes en cours</h2><p>Aucune alarme active.</p>';
        }
    }

    // Changement de lignes par page
    if (parPageSelect) {
        parPageSelect.addEventListener('change', () => {
            if (typeof loadContent === 'function') {
                loadContent('content/alarmes.php?page=1&par_page=' + parPageSelect.value);
            }
        });
    }
})();
</script>