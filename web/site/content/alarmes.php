<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo '<p>Non autorisé.</p>'; exit;
}
require_once __DIR__ . '/../config/config.php';

$pdo = get_pdo('app');

$stmt = $pdo->query('
    SELECT id_error, type_erreur, message, valeur, erreur_a
    FROM error
    WHERE acquittee = 0
    ORDER BY erreur_a DESC
');
$alarmes = $stmt->fetchAll();
?>

<section class="detail-serre">
    <h2>Alarmes en cours</h2>

    <?php if (empty($alarmes)): ?>
        <p>Aucune alarme active.</p>
    <?php else: ?>
    <table class="detail-table" id="tableAlarmes">
        <thead>
            <tr>
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
                <td><?= htmlspecialchars($a['erreur_a']) ?></td>
                <td><?= htmlspecialchars($a['type_erreur']) ?></td>
                <td><?= htmlspecialchars($a['message']) ?></td>
                <td><?= htmlspecialchars($a['valeur'] ?? '—') ?></td>
                <td>
                    <button class="btn-acquitter"
                            data-id="<?= (int)$a['id_error'] ?>">
                        Acquitter
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
    document.querySelectorAll('.btn-acquitter').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const res = await fetch('/content/alarme_acquitter.php', {
                method: 'POST',
                body: new URLSearchParams({ id }),
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('alarme-' + id).remove();
                const tbody = document.querySelector('#tableAlarmes tbody');
                if (tbody && tbody.children.length === 0) {
                    document.getElementById('tableAlarmes').outerHTML = '<p>Aucune alarme active.</p>';
                }
            } else {
                alert(data.errors.join('\n'));
            }
        });
    });
})();
</script>