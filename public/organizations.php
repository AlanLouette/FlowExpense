<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $prefix = trim($_POST['number_prefix'] ?? 'EXP');
        if ($name === '') {
            http_response_code(400);
            exit(t('Naam is verplicht.'));
        }
        $stmt = $db->prepare('INSERT INTO organizations (name, legal_name, number_prefix) VALUES (?, ?, ?)');
        $stmt->execute([$name, $name, $prefix ?: 'EXP']);
        $newOrgId = (int)$db->lastInsertId();
        $link = $db->prepare('INSERT OR IGNORE INTO user_organizations (user_id, organization_id, role) VALUES (?, ?, ?)');
        $link->execute([(int)$currentUser['id'], $newOrgId, 'admin']);
    } elseif ($action === 'update') {
        $orgId = isset($_POST['organization_id']) ? (int)$_POST['organization_id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $prefix = trim($_POST['number_prefix'] ?? 'EXP');
        if ($orgId <= 0 || $name === '') {
            http_response_code(400);
            exit(t('Ongeldige organisatie.'));
        }
        $stmt = $db->prepare('UPDATE organizations SET name = ?, legal_name = ? WHERE id = ?');
        $stmt->execute([$name, $name, $orgId]);
        $db->prepare('UPDATE organizations SET number_prefix = ? WHERE id = ?')->execute([$prefix ?: 'EXP', $orgId]);
    }

    header('Location: organizations.php');
    exit;
}

$organizations = $db->query('SELECT * FROM organizations ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

renderPageStart(t('Organisaties'), 'organizations');
?>
<section class="card">
    <h2><?= ht('Nieuwe organisatie') ?></h2>
    <form method="post"><?php csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <div class="grid-2">
            <label><?= ht('Naam') ?>
                <input type="text" name="name" required>
            </label>
            <label><?= ht('Nummer prefix') ?>
                <input type="text" name="number_prefix" value="EXP">
            </label>
        </div>
        <div style="display:flex;justify-content:flex-end;">
            <button type="submit"><?= ht('Toevoegen') ?></button>
        </div>
    </form>
</section>

<section class="card">
    <h2><?= ht('Bestaande organisaties') ?></h2>
    <table>
        <thead>
        <tr>
            <th><?= ht('Naam') ?></th>
            <th><?= ht('Prefix') ?></th>
            <th><?= ht('Laatste nummer') ?></th>
            <th><?= ht('Acties') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($organizations as $org): ?>
            <tr>
                <td><?= htmlspecialchars($org['name']) ?></td>
                <td><?= htmlspecialchars($org['number_prefix'] ?? 'EXP') ?></td>
                <td><?= htmlspecialchars($org['next_expense_number'] ?? 1) ?></td>
                <td>
                    <form method="post" style="display:flex;gap:8px;align-items:center;"><?php csrf_field(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="organization_id" value="<?= (int)$org['id'] ?>">
                        <input type="text" name="name" value="<?= htmlspecialchars($org['name']) ?>" required style="padding:6px 8px;border-radius:6px;border:1px solid var(--color-border);">
                        <input type="text" name="number_prefix" value="<?= htmlspecialchars($org['number_prefix'] ?? 'EXP') ?>" style="padding:6px 8px;border-radius:6px;border:1px solid var(--color-border);">
                        <button type="submit"><?= ht('Opslaan') ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php
renderPageEnd();
