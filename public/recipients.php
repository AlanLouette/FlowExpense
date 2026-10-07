<?php
require_once __DIR__ . '/bootstrap.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);
require_once __DIR__ . '/layout.php';

$orgId = (int)$currentOrganization['id'];
$isAdmin = $currentUser && (int)$currentUser['is_admin'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $deleteId=(int)$_POST['delete_id'];
        $db->prepare('DELETE FROM recipients WHERE id=? AND organization_id=?')->execute([$deleteId,$orgId]);
        record_event('recipient_deleted',null,['recipient_id'=>$deleteId]);
        header('Location: recipients.php'); exit;
    }
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $iban = trim($_POST['iban'] ?? '');
    $bank = trim($_POST['bank_name'] ?? '');

    if ($name === '' || $address === '' || $iban === '' || $bank === '') {
        http_response_code(400);
        exit(t('Alle velden zijn verplicht.'));
    }

    if ($id) {
        $stmt = $db->prepare('UPDATE recipients SET name = ?, address = ?, iban = ?, bank_name = ? WHERE id = ? AND organization_id = ?');
        $stmt->execute([$name, $address, $iban, $bank, $id, $orgId]);
    } else {
        $stmt = $db->prepare('INSERT INTO recipients (organization_id, name, address, iban, bank_name) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$orgId, $name, $address, $iban, $bank]);
    }

    header('Location: recipients.php');
    exit;
}


$editRecipient = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM recipients WHERE id = ? AND organization_id = ?');
    $stmt->execute([$editId, $orgId]);
    $editRecipient = $stmt->fetch(PDO::FETCH_ASSOC);
}

$stmt = $db->prepare('SELECT * FROM recipients WHERE organization_id = ? ORDER BY name ASC');
$stmt->execute([$orgId]);
$recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart(t('Ontvangers'), 'recipients');
?>
<section class="card">
    <h2><?= $editRecipient ? t('Ontvanger bijwerken') : t('Nieuwe ontvanger') ?></h2>
    <form method="post"><?php csrf_field(); ?>
        <div class="grid-2">
            <label><?= ht('Naam') ?>
                <input type="text" name="name" required value="<?= htmlspecialchars($editRecipient['name'] ?? '') ?>">
            </label>
            <label><?= ht('Adres') ?>
                <input type="text" name="address" required value="<?= htmlspecialchars($editRecipient['address'] ?? '') ?>">
            </label>
            <label><?= ht('IBAN') ?>
                <input type="text" name="iban" required value="<?= htmlspecialchars($editRecipient['iban'] ?? '') ?>">
            </label>
            <label><?= ht('Bank') ?>
                <input type="text" name="bank_name" required value="<?= htmlspecialchars($editRecipient['bank_name'] ?? '') ?>">
            </label>
        </div>
        <?php if ($editRecipient): ?>
            <input type="hidden" name="id" value="<?= (int)$editRecipient['id'] ?>">
        <?php endif; ?>
        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <button type="submit"><?= $editRecipient ? t('Bijwerken') : t('Toevoegen') ?></button>
            <?php if ($editRecipient): ?>
                <a class="button" href="recipients.php" style="display:inline-flex;align-items:center;background:linear-gradient(135deg,#9ca3af,#6b7280);padding:10px 18px;"><?= ht('Annuleren') ?></a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="card">
    <h2><?= ht('Huidige ontvangers') ?></h2>
    <?php if (empty($recipients)): ?>
        <p class="notice"><?= ht('Er zijn nog geen ontvangers aangemaakt voor deze organisatie.') ?></p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th><?= ht('Naam') ?></th>
                <th><?= ht('Adres') ?></th>
                <th><?= ht('IBAN') ?></th>
                <th><?= ht('Bank') ?></th>
                <th><?= ht('Acties') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recipients as $recipient): ?>
                <tr>
                    <td><?= htmlspecialchars($recipient['name']) ?></td>
                    <td><?= htmlspecialchars($recipient['address']) ?></td>
                    <td><?= htmlspecialchars($recipient['iban']) ?></td>
                    <td><?= htmlspecialchars($recipient['bank_name']) ?></td>
                    <td class="table-actions">
                        <a href="recipients.php?edit=<?= (int)$recipient['id'] ?>"><?= ht('Bewerken') ?></a>
                        <a href="recipient-detail.php?id=<?= (int)$recipient['id'] ?>"><?= ht('Bekijk') ?></a>
                        <a href="recipient-export.php?id=<?= (int)$recipient['id'] ?>"><?= ht('Export') ?></a>
                        <form method="post" class="inline-action" onsubmit="return confirm(<?= htmlspecialchars(jt('Ontvanger verwijderen?'), ENT_QUOTES) ?>)"><?php csrf_field(); ?>
                            <input type="hidden" name="delete_id" value="<?= (int)$recipient['id'] ?>"><button type="submit" class="danger-link"><?= ht('Verwijderen') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php
renderPageEnd();
