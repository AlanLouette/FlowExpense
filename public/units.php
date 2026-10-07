<?php
require_once __DIR__ . '/bootstrap.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);
require_once __DIR__ . '/layout.php';

$orgId = (int)$currentOrganization['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $deleteId = (int)$_POST['delete_id'];
        $stmt = $db->prepare('DELETE FROM units WHERE id = ? AND organization_id = ?');
        $stmt->execute([$deleteId, $orgId]);
        header('Location: units.php');
        exit;
    }

    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        http_response_code(400);
        exit(t('Naam is verplicht.'));
    }

    if ($id) {
        $stmt = $db->prepare('UPDATE units SET name = ? WHERE id = ? AND organization_id = ?');
        $stmt->execute([$name, $id, $orgId]);
    } else {
        $stmt = $db->prepare('INSERT OR IGNORE INTO units (organization_id, name) VALUES (?, ?)');
        $stmt->execute([$orgId, $name]);
    }

    header('Location: units.php');
    exit;
}

$editUnit = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM units WHERE id = ? AND organization_id = ?');
    $stmt->execute([$editId, $orgId]);
    $editUnit = $stmt->fetch(PDO::FETCH_ASSOC);
}

$stmt = $db->prepare('SELECT * FROM units WHERE organization_id = ? ORDER BY name ASC');
$stmt->execute([$orgId]);
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart(t('Eenheden'), 'units');
?>
<section class="card">
    <h2><?= $editUnit ? t('Eenheid bijwerken') : t('Nieuwe eenheid') ?></h2>
    <form method="post"><?php csrf_field(); ?>
        <label><?= ht('Naam') ?>
            <input type="text" name="name" required value="<?= htmlspecialchars($editUnit['name'] ?? '') ?>">
        </label>
        <?php if ($editUnit): ?>
            <input type="hidden" name="id" value="<?= (int)$editUnit['id'] ?>">
        <?php endif; ?>
        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <button type="submit"><?= $editUnit ? t('Bijwerken') : t('Toevoegen') ?></button>
            <?php if ($editUnit): ?>
                <a class="button" href="units.php" style="display:inline-flex;align-items:center;background:linear-gradient(135deg,#9ca3af,#6b7280);padding:10px 18px;"><?= ht('Annuleren') ?></a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="card">
    <h2><?= ht('Beschikbare eenheden') ?></h2>
    <?php if (empty($units)): ?>
        <p class="notice"><?= ht('Er zijn nog geen eenheden gedefinieerd.') ?></p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th><?= ht('Naam') ?></th>
                <th><?= ht('Acties') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($units as $unit): ?>
                <tr>
                    <td><?= htmlspecialchars($unit['name']) ?></td>
                    <td class="table-actions">
                        <a href="units.php?edit=<?= (int)$unit['id'] ?>"><?= ht('Bewerken') ?></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm(<?= htmlspecialchars(jt('Eenheid verwijderen?'), ENT_QUOTES) ?>);"><?php csrf_field(); ?>
                            <input type="hidden" name="delete_id" value="<?= (int)$unit['id'] ?>">
                            <button type="submit" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;padding:6px 12px;border-radius:8px;border:none;cursor:pointer;"><?= ht('Verwijderen') ?></button>
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
