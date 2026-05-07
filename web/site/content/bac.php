<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<p>Non autorisé.</p>'; exit;
}
require_once __DIR__ . '/../config/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { echo '<p>Bac introuvable.</p>'; exit; }

$pdo = get_pdo('app');

// Fetch bac — verify ownership via serre→cree_par
$stmt = $pdo->prepare('
    SELECT b.*, s.nom AS serre_nom, s.id_serre
    FROM bac b
    JOIN serre s ON s.id_serre = b.serre
    WHERE b.id_bac = ? AND s.cree_par = ?
');
$stmt->execute([$id, $_SESSION['id']]);
$bac = $stmt->fetch();
if (!$bac) { echo '<p>Bac introuvable.</p>'; exit; }

// Fetch filled blocs — index by [y][x]
$stmtBlocs = $pdo->prepare('
    SELECT bl.id_bloc, bl.x, bl.y, bl.plante_a,
           c.id_culture, c.plante, c.icone
    FROM bloc bl
    JOIN culture c ON c.id_culture = bl.culture
    WHERE bl.bac = ?
');
$stmtBlocs->execute([$id]);
$grid = [];
foreach ($stmtBlocs->fetchAll() as $bloc) {
    $grid[$bloc['y']][$bloc['x']] = $bloc;
}

// Fetch all cultures available to current user (for the popup dropdown)
$stmtCultures = $pdo->prepare('
    SELECT id_culture, plante
    FROM culture
    WHERE cree_par = ?
    ORDER BY plante ASC
');
$stmtCultures->execute([$_SESSION['id']]);
$cultures = $stmtCultures->fetchAll();

// Generate a simple color per culture id (for filled bloc background)
function blocColor(int $id): string {
    $colors = ['#a8d5a2','#f6d365','#f7a072','#b5ead7','#c7ceea','#ffdac1','#e2f0cb','#b5d5c5'];
    return $colors[$id % count($colors)];
}
?>

<section class="detail-bac">
    <h2>Bac N°<?= (int)$bac['numero'] ?></h2>
    <p class="breadcrumb">
        ← <span data-action="content"
                data-target="content/serre.php?id=<?= (int)$bac['id_serre'] ?>"
                style="cursor:pointer;text-decoration:underline;">
            <?= htmlspecialchars($bac['serre_nom']) ?>
        </span>
    </p>

    <table class="detail-table">
        <tr><th>Taille</th><td><?= (int)$bac['x_taille'] ?> × <?= (int)$bac['y_taille'] ?> blocs</td></tr>
        <tr><th>Arrosage</th><td><?= $bac['arrose'] ? '💧 Activé' : 'Désactivé' ?></td></tr>
    </table>

    <h3>Grille des blocs</h3>

    <!-- GRID -->
    <div class="bloc-grid"
         id="blocGrid"
         data-bac-id="<?= (int)$id ?>"
         style="
            display: grid;
            grid-template-columns: repeat(<?= (int)$bac['x_taille'] ?>, 6rem);
            grid-template-rows:    repeat(<?= (int)$bac['y_taille'] ?>, 6rem);
            gap: 4px;
            width: fit-content;
        ">

        <?php for ($y = 0; $y < $bac['y_taille']; $y++):
              for ($x = 0; $x < $bac['x_taille']; $x++):
                  $bloc = $grid[$y][$x] ?? null;
        ?>

        <?php if ($bloc): ?>
            <!-- FILLED BLOC -->
        <div class="bloc bloc-filled"
             data-x="<?= $x ?>"
             data-y="<?= $y ?>"
             data-bloc-id="<?= (int)$bloc['id_bloc'] ?>"
             style="
                background: <?= blocColor((int)$bloc['id_culture']) ?>;
             "
             title="<?= htmlspecialchars($bloc['plante']) ?> — planté le <?= $bloc['plante_a'] ?>">

            <span class="bloc-name"><?= htmlspecialchars(mb_substr($bloc['plante'], 0, 6)) ?></span>

            <?php if (!empty($bloc['icone'])): ?>
                <img src="<?= htmlspecialchars($bloc['icone']) ?>"
                     alt="<?= htmlspecialchars($bloc['plante']) ?>"
                     style="width:60%;height:60%;object-fit:contain;display:block;">
            <?php endif; ?>
            
            <button class="bloc-remove"
                    data-bloc-id="<?= (int)$bloc['id_bloc'] ?>"
                    data-x="<?= $x ?>"
                    data-y="<?= $y ?>"
                    data-plant-name="<?= htmlspecialchars($bloc['plante']) ?>"
                    title="Retirer la culture">✕</button>
        </div>

        <?php else: ?>
            <!-- EMPTY BLOC -->
            <div class="bloc bloc-empty"
                 data-x="<?= $x ?>"
                 data-y="<?= $y ?>"
                 title="Bloc vide — cliquer pour assigner une culture">
                <button class="bloc-add"
                        data-x="<?= $x ?>"
                        data-y="<?= $y ?>">+</button>
            </div>

        <?php endif; ?>

        <?php endfor; endfor; ?>
    </div>

    <!-- CULTURE POPUP (hidden, positioned by JS near clicked bloc) -->
    <div id="culturePopup" style="display:none;">
        <div id="culturePopupInner">
            <p><strong>Assigner une culture</strong></p>

            <?php if (empty($cultures)): ?>
                <p>Aucune culture disponible.</p>
                <span data-action="modal"
                      data-target="forms/new/new_culture.html"
                      style="cursor:pointer;color:blue;text-decoration:underline;">
                    + Créer une culture
                </span>
            <?php else: ?>
                <select id="cultureSelect">
                    <option value="">— Choisir —</option>
                    <?php foreach ($cultures as $c): ?>
                    <option value="<?= (int)$c['id_culture'] ?>">
                        <?= htmlspecialchars($c['plante']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button id="cultureAssignBtn">Assigner</button>
                <span id="popupNewCulture"
                      data-action="modal"
                      data-target="forms/new/new_culture.html"
                      style="cursor:pointer;color:blue;text-decoration:underline;display:block;margin-top:6px;">
                    + Nouvelle culture
                </span>
            <?php endif; ?>

            <button id="culturePopupClose">Fermer</button>
            <!-- JS stores clicked x/y here -->
            <input type="hidden" id="popupX" value="" />
            <input type="hidden" id="popupY" value="" />
        </div>
    </div>

</section>

<style>
.bloc-grid { margin-top: 12px; }

.bloc {
    width: 6rem;
    height: 6rem;
    border: 1px solid #aaa;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    position: relative;
    cursor: pointer;
}

.bloc-empty {
    background: #f0f0f0;
}
.bloc-empty:hover {
    background: #ddd;
}

.bloc-add {
    background: none;
    border: none;
    font-size: 1.4rem;
    cursor: pointer;
    color: #555;
    line-height: 1;
}

.bloc-filled .bloc-name {
    font-weight: bold;
    text-align: center;
    font-size: 1rem;
}

.bloc-remove {
    display: none;
    position: absolute;
    top: 1px;
    right: 1px;
    background: rgba(255,255,255,0.7);
    border: none;
    border-radius: 50%;
    font-size: .55rem;
    cursor: pointer;
    width: 14px;
    height: 14px;
    line-height: 14px;
    padding: 0;
    text-align: center;
}
.bloc-filled:hover .bloc-remove {
    display: block;
}

/* Popup */
#culturePopup {
    position: fixed;
    z-index: 2000;
    background: white;
    border: 1px solid #aaa;
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    min-width: 200px;
}
#cultureSelect {
    display: block;
    width: 100%;
    margin-bottom: 6px;
}
#cultureAssignBtn {
    margin-right: 6px;
}
#culturePopupClose {
    margin-top: 6px;
}
</style>

<script>
(function() {
    const grid    = document.getElementById('blocGrid');
    const popup   = document.getElementById('culturePopup');
    const bacId   = parseInt(grid.dataset.bacId);

    // ── OPEN POPUP on any bloc click (empty or filled) ──────
    grid.addEventListener('click', (e) => {
        // Ignore clicks on the remove button
        if (e.target.closest('.bloc-remove')) return;

        const bloc = e.target.closest('.bloc');
        if (!bloc) return;

        document.getElementById('popupX').value = bloc.dataset.x;
        document.getElementById('popupY').value = bloc.dataset.y;

        // Position popup near the bloc
        const rect = bloc.getBoundingClientRect();
        popup.style.top  = (rect.bottom + window.scrollY + 6) + 'px';
        popup.style.left = (rect.left  + window.scrollX)      + 'px';
        popup.style.display = 'block';
    });

    // ── CLOSE POPUP ─────────────────────────────────────────
    document.getElementById('culturePopupClose')
        .addEventListener('click', () => popup.style.display = 'none');

    // ── ASSIGN culture ───────────────────────────────────────
    const assignBtn = document.getElementById('cultureAssignBtn');
    if (assignBtn) {
        assignBtn.addEventListener('click', async () => {
            const cultureId = document.getElementById('cultureSelect').value;
            const x = document.getElementById('popupX').value;
            const y = document.getElementById('popupY').value;

            if (!cultureId) return;

            const res = await fetch('/content/bloc/assign.php', {
                method: 'POST',
                body: new URLSearchParams({ bac: bacId, culture: cultureId, x, y }),
            });
            const data = await res.json();

            if (data.success) {
                popup.style.display = 'none';
                if (typeof loadContent === 'function') {
                    loadContent('content/bac.php?id=' + bacId);
                }
            } else {
                alert(data.errors.join('\n'));
            }
        });
    }

    // ── REMOVE culture ───────────────────────────────────────
    grid.addEventListener('click', async (e) => {
        const removeBtn = e.target.closest('.bloc-remove');
        if (!removeBtn) return;

        const blocId    = removeBtn.dataset.blocId;
        const plantName = removeBtn.dataset.plantName;

        // Confirmation before deletion
        const confirmed = confirm(`Retirer "${plantName}" de ce bloc ?`);
        if (!confirmed) return;

        const res = await fetch('/content/bloc/remove.php', {
            method: 'POST',
            body: new URLSearchParams({ bloc_id: blocId }),
        });
        const data = await res.json();

        if (data.success) {
            if (typeof loadContent === 'function') {
                loadContent('content/bac.php?id=' + bacId);
            }
        } else {
            alert(data.errors.join('\n'));
        }
    });

    // ── NEW CULTURE link inside popup ────────────────────────
    const newCultureLink = document.getElementById('popupNewCulture');
    if (newCultureLink) {
        newCultureLink.addEventListener('click', () => {
            popup.style.display = 'none';
            if (typeof openModal === 'function') {
                openModal('forms/new/new_culture.html');
            }
        });
    }

})();
</script>