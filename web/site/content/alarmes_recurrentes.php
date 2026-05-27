<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<p>Non autorisé.</p>'; exit;
}
require_once __DIR__ . '/../config/config.php';

define('SEUIL_RECURRENCE', 3);

$pdo = get_pdo('app');
$stmt = $pdo->prepare('
    SELECT type_erreur, COUNT(*) AS nb,
           MIN(erreur_a) AS premiere_occurrence,
           MAX(erreur_a) AS derniere_occurrence
    FROM error
    WHERE acquittee = 0
    GROUP BY type_erreur
    HAVING nb >= ?
    ORDER BY nb DESC
');
$stmt->execute([SEUIL_RECURRENCE]);
$alarmes = $stmt->fetchAll();

function categorieErreur(string $type): string {
    $conf = ['BAC_NOT_FOUND','UNKNOWN_SENSOR','INVALID_PAYLOAD',
             'INVALID_VALUE','VALUE_OUT_OF_RANGE','INVALID_ENCODING'];
    $tech = ['DB_ERROR','DB_INSERT_ERROR'];
    $sys  = ['MQTT_DISCONNECT','UNEXPECTED_ERROR'];
    if (in_array($type, $conf)) return 'Configuration';
    if (in_array($type, $tech)) return 'Technique';
    if (in_array($type, $sys))  return 'Système';
    return 'Inconnue';
}
?>

<section class="detail-serre">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <h2 style="margin:0;">Alarmes actives</h2>
        <span style="cursor:pointer; text-decoration:underline;"
              data-action="content"
              data-target="content/erreurs.php">
            Voir toutes les erreurs →
        </span>
    </div>

    <p>Erreurs apparaissant au moins <?= SEUIL_RECURRENCE ?> fois et non acquittées.</p>

    <?php if (empty($alarmes)): ?>
        <p>Aucune alarme active.</p>
    <?php else: ?>
    <table class="detail-table">
        <thead>
            <tr>
                <th>Catégorie</th>
                <th>Type d'erreur</th>
                <th>Occurrences</th>
                <th>Première apparition</th>
                <th>Dernière apparition</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($alarmes as $a): ?>
            <tr>
                <td><?= htmlspecialchars(categorieErreur($a['type_erreur'])) ?></td>
                <td><?= htmlspecialchars($a['type_erreur']) ?></td>
                <td><?= (int)$a['nb'] ?></td>
                <td><?= htmlspecialchars($a['premiere_occurrence']) ?></td>
                <td><?= htmlspecialchars($a['derniere_occurrence']) ?></td>
                <td>
                    <button class="btn-acquitter-type"
                            data-type="<?= htmlspecialchars($a['type_erreur']) ?>">
                        Acquitter tout
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<script>
(function () {
    document.querySelectorAll('.btn-acquitter-type').forEach(btn => {
        btn.addEventListener('click', async () => {
            const type = btn.dataset.type;
            const res  = await fetch('/content/alarme_acquitter_type.php', {
                method: 'POST',
                body: new URLSearchParams({ type_erreur: type }),
            });
            const data = await res.json();
            if (data.success) {
                btn.closest('tr').remove();
                // Retire le badge si plus d'alarmes
                const tbody = document.querySelector('.detail-table tbody');
                if (tbody && tbody.children.length === 0) {
                    document.querySelector('.detail-serre').innerHTML =
                        '<h2>Alarmes actives</h2><p>Aucune alarme active.</p>';
                }
                // Met à jour le badge dans la navbar
                if (typeof rafraichirBadgeAlarmes === 'function') rafraichirBadgeAlarmes();
            } else {
                alert(data.errors.join('\n'));
            }
        });
    });
})();
</script>