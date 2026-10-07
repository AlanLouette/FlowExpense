<?php
require_once __DIR__ . '/bootstrap.php';
requireAdmin();
enforceOrganizationAccess($currentOrganization ?? null);
require_once __DIR__ . '/layout.php';

$allOrganizations = $db->query('SELECT id, name FROM organizations ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
        $orgIds = array_map('intval', $_POST['organization_ids'] ?? []);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            http_response_code(400);
            exit(t('Alle velden zijn verplicht.'));
        }

        $db->beginTransaction();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, is_admin, active) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([$name, $email, $hash, $isAdmin]);
        $userId = (int)$db->lastInsertId();

        $insertMembership = $db->prepare('INSERT OR IGNORE INTO user_organizations (user_id, organization_id, role) VALUES (?, ?, ?)');
        foreach ($orgIds as $orgId) {
            $insertMembership->execute([$userId, $orgId, $isAdmin ? 'admin' : 'member']);
        }

        record_event('user_created',null,['user_id'=>$userId,'name'=>$name,'is_admin'=>$isAdmin]);
        $db->commit();
        header('Location: users.php');
        exit;
    }

    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if ($userId <= 0) {
        http_response_code(400);
        exit(t('Ongeldige gebruiker.'));
    }

    $db->beginTransaction();
    if ($action === 'toggle_active') {
        if ($userId === (int)$currentUser['id']) { $db->rollBack(); http_response_code(400); exit(t('You cannot disable your own account.')); }
        $db->prepare('UPDATE users SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END, session_version=session_version+1 WHERE id = ?')->execute([$userId]);
    } elseif ($action === 'toggle_admin') {
        if ($userId === (int)$currentUser['id']) {
            http_response_code(400);
            exit(t('Kan eigen adminstatus niet wijzigen.'));
        }
        $db->prepare('UPDATE users SET is_admin = CASE WHEN is_admin = 1 THEN 0 ELSE 1 END, session_version=session_version+1 WHERE id = ?')->execute([$userId]);
    } elseif ($action === 'reset_password') {
        $password = $_POST['new_password'] ?? '';
        if (strlen($password) < 12) {
            http_response_code(400);
            exit(t('Use a password of at least 12 characters.'));
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare('UPDATE users SET password_hash = ?, session_version=session_version+1 WHERE id = ?')->execute([$hash, $userId]);
    } elseif ($action === 'update_membership') {
        $orgIds = array_map('intval', $_POST['organization_ids'] ?? []);
        if ($userId === (int)$currentUser['id'] && !in_array((int)$currentOrganization['id'],$orgIds,true)) { $db->rollBack();http_response_code(400);exit(t('Keep access to your current organization.')); }
        $db->prepare('DELETE FROM user_organizations WHERE user_id = ?')->execute([$userId]);
        $user = $db->prepare('SELECT is_admin FROM users WHERE id = ?');
        $user->execute([$userId]);
        $userData = $user->fetch(PDO::FETCH_ASSOC);
        $role = (!empty($userData) && (int)$userData['is_admin'] === 1) ? 'admin' : 'member';
        $insertMembership = $db->prepare('INSERT OR IGNORE INTO user_organizations (user_id, organization_id, role) VALUES (?, ?, ?)');
        foreach ($orgIds as $orgId) {
            $insertMembership->execute([$userId, $orgId, $role]);
        }
    }

    record_event('user_updated',null,['user_id'=>$userId,'action'=>$action]);
    $db->commit();
    header('Location: users.php');
    exit;
}

$users = $db->query('SELECT * FROM users ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$userOrgMap = [];
if ($users) {
    $userIds = array_column($users, 'id');
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $db->prepare("SELECT uo.user_id, o.id AS org_id, o.name FROM user_organizations uo INNER JOIN organizations o ON o.id = uo.organization_id WHERE uo.user_id IN ($placeholders) ORDER BY o.name");
    $stmt->execute($userIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $userOrgMap[(int)$row['user_id']][] = $row;
    }
}

renderPageStart(t('Gebruikersbeheer'), 'users');
?>
<div class="users-page">
<section class="card">
    <h2><?= ht('Nieuwe gebruiker') ?></h2>
    <form method="post" class="user-form"><?php csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <div class="user-create-fields">
            <label><?= ht('Naam') ?><input type="text" name="name" autocomplete="name" required></label>
            <label><?= ht('E-mailadres') ?><input type="email" name="email" autocomplete="email" required></label>
            <label><?= ht('Wachtwoord') ?><input type="password" name="password" autocomplete="new-password" minlength="12" required><small><?= ht('Use a password of at least 12 characters.') ?></small></label>
        </div>
        <div class="user-create-options">
            <fieldset class="user-organizations"><legend><?= ht('Organisaties') ?></legend>
                <div class="user-checks">
                <?php foreach ($allOrganizations as $org): ?>
                    <label class="user-check"><input type="checkbox" name="organization_ids[]" value="<?= (int)$org['id'] ?>" <?= (int)$org['id'] === (int)$currentOrganization['id'] ? 'checked' : '' ?>><?= htmlspecialchars($org['name']) ?></label>
                <?php endforeach; ?>
                </div>
            </fieldset>
            <label class="user-check"><input type="checkbox" name="is_admin" value="1"><?= ht('Beheerder') ?></label>
            <button type="submit"><?= ht('Gebruiker aanmaken') ?></button>
        </div>
    </form>
</section>
<section class="card">
    <h2><?= ht('Bestaande gebruikers') ?></h2>
    <div class="user-list">
    <?php foreach ($users as $user): ?>
        <?php $userId = (int)$user['id']; $assigned = $userOrgMap[$userId] ?? []; $assignedIds = array_map('intval', array_column($assigned, 'org_id')); $isSelf = $userId === (int)$currentUser['id']; ?>
        <article class="user-entry">
            <div class="user-overview">
                <div class="user-identity"><strong><?= htmlspecialchars($user['name']) ?></strong><span><?= htmlspecialchars($user['email']) ?></span></div>
                <div class="user-labels"><span class="badge"><?= ht((int)$user['is_admin'] === 1 ? 'Beheerder' : 'Gebruiker') ?></span><span class="user-status <?= (int)$user['active'] === 1 ? 'is-active' : '' ?>"><?= ht((int)$user['active'] === 1 ? 'Actief' : 'Inactief') ?></span></div>
            </div>
            <div class="user-management">
                <form method="post" class="user-form user-membership"><?php csrf_field(); ?>
                    <input type="hidden" name="action" value="update_membership"><input type="hidden" name="user_id" value="<?= $userId ?>">
                    <fieldset class="user-organizations"><legend><?= ht('Organisaties') ?></legend><div class="user-checks">
                    <?php foreach ($allOrganizations as $org): ?>
                        <label class="user-check"><input type="checkbox" name="organization_ids[]" value="<?= (int)$org['id'] ?>" <?= in_array((int)$org['id'], $assignedIds, true) ? 'checked' : '' ?>><?= htmlspecialchars($org['name']) ?></label>
                    <?php endforeach; ?>
                    </div></fieldset>
                    <button type="submit" class="user-secondary"><?= ht('Opslaan') ?></button>
                </form>
                <div class="user-account-actions">
                    <form method="post" class="user-form user-reset" onsubmit="return confirm(<?= htmlspecialchars(jt('Wachtwoord resetten?'), ENT_QUOTES) ?>);"><?php csrf_field(); ?>
                        <input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?= $userId ?>">
                        <label for="password-<?= $userId ?>"><?= ht('Nieuw wachtwoord') ?></label>
                        <div class="user-reset-controls"><input id="password-<?= $userId ?>" type="password" name="new_password" autocomplete="new-password" minlength="12" placeholder="<?= ht('Nieuw wachtwoord') ?>" required><button type="submit" class="user-secondary"><?= ht('Reset') ?></button></div>
                    </form>
                    <div class="user-toggle-actions">
                        <form method="post" class="user-form"><?php csrf_field(); ?><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="user_id" value="<?= $userId ?>"><button type="submit" class="user-text-action" <?= $isSelf ? 'disabled' : '' ?>><?= ht((int)$user['active'] === 1 ? 'Deactiveer' : 'Activeer') ?></button></form>
                        <form method="post" class="user-form"><?php csrf_field(); ?><input type="hidden" name="action" value="toggle_admin"><input type="hidden" name="user_id" value="<?= $userId ?>"><button type="submit" class="user-text-action" <?= $isSelf ? 'disabled' : '' ?>><?= ht((int)$user['is_admin'] === 1 ? 'Maak gebruiker' : 'Maak admin') ?></button></form>
                    </div>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</section>
</div>
<?php renderPageEnd(); ?>
