<?php
require_once __DIR__ . '/bootstrap.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);
require_once __DIR__ . '/layout.php';

$orgId = (int)$currentOrganization['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $deleteId=(int)$_POST['delete_id'];
        $db->beginTransaction();
        $db->prepare('UPDATE expense_reports SET category_id=NULL WHERE category_id=? AND organization_id=?')->execute([$deleteId,$orgId]);
        $db->prepare('DELETE FROM expense_categories WHERE id=? AND organization_id=?')->execute([$deleteId,$orgId]);
        record_event('category_deleted',null,['category_id'=>$deleteId]);
        $db->commit();
        header('Location: categories.php'); exit;
    }
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        http_response_code(400);
        exit(t('Naam is verplicht.'));
    }

    if ($id) {
        $stmt = $db->prepare('UPDATE expense_categories SET name = ? WHERE id = ? AND organization_id = ?');
        $stmt->execute([$name, $id, $orgId]);
    } else {
        $stmt = $db->prepare('INSERT INTO expense_categories (organization_id, name) VALUES (?, ?)');
        $stmt->execute([$orgId, $name]);
    }

    header('Location: categories.php');
    exit;
}


$editCategory = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM expense_categories WHERE id = ? AND organization_id = ?');
    $stmt->execute([$editId, $orgId]);
    $editCategory = $stmt->fetch(PDO::FETCH_ASSOC);
}

$stmt = $db->prepare('SELECT * FROM expense_categories WHERE organization_id = ? ORDER BY name ASC');
$stmt->execute([$orgId]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

renderPageStart(t('Categorieën'), 'categories');
?>
<section class="card">
    <h2><?= $editCategory ? t('Categorie bijwerken') : t('Nieuwe categorie') ?></h2>
    <form method="post"><?php csrf_field(); ?>
        <label><?= ht('Naam') ?>
            <input type="text" name="name" required value="<?= htmlspecialchars($editCategory['name'] ?? '') ?>">
        </label>
        <?php if ($editCategory): ?>
            <input type="hidden" name="id" value="<?= (int)$editCategory['id'] ?>">
        <?php endif; ?>
        <div style="display:flex;gap:12px;justify-content:flex-end;">
            <button type="submit"><?= $editCategory ? t('Bijwerken') : t('Toevoegen') ?></button>
            <?php if ($editCategory): ?>
                <a class="button" href="categories.php" style="display:inline-flex;align-items:center;background:linear-gradient(135deg,#9ca3af,#6b7280);padding:10px 18px;"><?= ht('Annuleren') ?></a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="card">
    <h2><?= ht('Bestaande categorieën') ?></h2>
    <?php if (empty($categories)): ?>
        <p class="notice"><?= ht('Er zijn nog geen categorieën aangemaakt.') ?></p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th><?= ht('Naam') ?></th>
                <th><?= ht('Acties') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $category): ?>
                <tr>
                    <td><?= htmlspecialchars($category['name']) ?></td>
                    <td class="table-actions">
                        <a href="categories.php?edit=<?= (int)$category['id'] ?>"><?= ht('Bewerken') ?></a>
                        <form method="post" class="inline-action" onsubmit="return confirm(<?= htmlspecialchars(jt('Categorie verwijderen? Gekoppelde onkostennota\'s verliezen hun categorie.'), ENT_QUOTES) ?>)"><?php csrf_field(); ?>
                            <input type="hidden" name="delete_id" value="<?= (int)$category['id'] ?>"><button type="submit" class="danger-link"><?= ht('Verwijderen') ?></button>
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
