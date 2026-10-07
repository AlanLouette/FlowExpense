<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/markdown.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);
require_once __DIR__ . '/layout.php';

$orgId = (int)$currentOrganization['id'];
$isAdmin = $currentUser && (int)$currentUser['is_admin'] === 1;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    exit(t('Ongeldig ID.'));
}

$stmt = $db->prepare('SELECT * FROM recipients WHERE id = ? AND organization_id = ?');
$stmt->execute([$id, $orgId]);
$recipient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recipient) {
    exit(t('Ontvanger niet gevonden.'));
}

$expenseQuery = 'SELECT * FROM expense_reports WHERE organization_id = ? AND deleted_at IS NULL AND TRIM(recipient) = TRIM(?)';
$params = [$orgId, $recipient['name']];
if (!$isAdmin) {
    $expenseQuery .= ' AND user_id = ?';
    $params[] = (int)$currentUser['id'];
}
$expenseQuery .= ' ORDER BY date DESC';
$expensesStmt = $db->prepare($expenseQuery);
$expensesStmt->execute($params);
$expenses = $expensesStmt->fetchAll(PDO::FETCH_ASSOC);

$expenseIds = array_column($expenses, 'id');
$attachments = [];
if ($expenseIds) {
    $placeholders = implode(',', array_fill(0, count($expenseIds), '?'));
    $attStmt = $db->prepare("SELECT report_id, COUNT(*) AS cnt FROM expense_attachments WHERE deleted_at IS NULL AND report_id IN ($placeholders) GROUP BY report_id");
    $attStmt->execute($expenseIds);
    foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $att) {
        $attachments[(int)$att['report_id']] = (int)$att['cnt'];
    }
}

$totalAmount = 0.0;
foreach ($expenses as $exp) {
    $totalAmount += (float)($exp['total'] ?? 0);
}

renderPageStart(t('Ontvanger: ') . ($recipient['name'] ?? ''), 'recipients');
?>
<section class="card">
    <h2><?= htmlspecialchars($recipient['name'] ?? '') ?></h2>
    <div class="grid-2">
        <div>
            <strong><?= ht('Adres') ?></strong>
            <p><?= nl2br(htmlspecialchars($recipient['address'] ?? '')) ?></p>
        </div>
        <div>
            <strong><?= ht('Bankgegevens') ?></strong>
            <p>IBAN: <?= htmlspecialchars($recipient['iban'] ?? '') ?><br><?= ht('Bank:') ?> <?= htmlspecialchars($recipient['bank_name'] ?? '') ?></p>
        </div>
    </div>
    <div class="notice" style="margin-top:16px;"><?= ht('Totaal bedrag aan kosten: €') ?> <?= localized_number($totalAmount, 2, ',', '.') ?></div>
</section>

<section class="card">
    <h2><?= ht('Onkostennota\'s') ?></h2>
    <?php if (empty($expenses)): ?>
        <p class="notice"><?= ht('Geen onkostennota\'s gevonden voor deze ontvanger.') ?></p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th><?= ht('Nummer') ?></th>
                <th><?= ht('Datum') ?></th>
                <th><?= ht('Totaal') ?></th>
                <th><?= ht('Status') ?></th>
                <th><?= ht('Omschrijving') ?></th>
                <th><?= ht('Bijlagen') ?></th>
                <th><?= ht('Acties') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($expenses as $expense): ?>
                <?php $desc = markdown_to_html($expense['description'] ?? ''); ?>
                <tr>
                    <td><?= htmlspecialchars($expense['custom_id'] ?: '#' . $expense['id']) ?></td>
                    <td><?= htmlspecialchars($expense['date'] ?? '') ?></td>
                    <td>€ <?= localized_number((float)$expense['total'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars(t($expense['status'] ?? 'Open')) ?></td>
                    <td><div style="max-width:240px; font-size:0.85rem; color:var(--color-muted);"><?= strip_tags($desc) ?></div></td>
                    <td><span class="badge">📎 <?= $attachments[(int)$expense['id']] ?? 0 ?></span></td>
                    <td class="table-actions">
                        <a href="form.php?id=<?= (int)$expense['id'] ?>"><?= ht('Openen') ?></a>
                        <a href="export.php?id=<?= (int)$expense['id'] ?>" target="_blank">PDF</a>
                        <form action="duplicate.php" method="post" class="inline-action"><?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int)$expense['id'] ?>"><button type="submit"><?= ht('Dupliceren') ?></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php
renderPageEnd();
