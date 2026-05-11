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
    WHERE acquittee = 1
    ORDER BY erreur_a DESC
');
$historique = $stmt->fetchAll();
?>

<section class="detail-serre">
    <h2>Historique des alarmes</h2>

    <?php if (empty($historique)): ?>
        <p>Aucune alarme dans l'historique.</p>
    <?php else: ?>
    <table class="detail-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Message</th>
                <th>Valeur reçue</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($historique as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['erreur_a']) ?></td>
                <td><?= htmlspecialchars($a['type_erreur']) ?></td>
                <td><?= htmlspecialchars($a['message']) ?></td>
                <td><?= htmlspecialchars($a['valeur'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>