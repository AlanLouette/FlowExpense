<?php
require_once __DIR__ . '/bootstrap.php';

if ($currentUser) {
    header('Location: index.php');
    exit;
}

$accountsReady = (int)$db->query('SELECT COUNT(*) FROM users WHERE is_admin=1 AND active=1')->fetchColumn() > 0;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $identifier = hash('sha256', strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
    $attempts = $db->prepare('SELECT * FROM login_attempts WHERE identifier=?');
    $attempts->execute([$identifier]); $attempt = $attempts->fetch(PDO::FETCH_ASSOC);
    if ($attempt && (int)$attempt['blocked_until'] > time()) {
        http_response_code(429); exit(t('Too many sign-in attempts. Try again in 15 minutes.'));
    }
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        if (getenv('APP_ENV') === 'production' && strlen($password) < 12) { http_response_code(403);exit(t('Use a password of at least 12 characters.')); }
        session_regenerate_id(true);
        $db->prepare('DELETE FROM login_attempts WHERE identifier=?')->execute([$identifier]);
        $_SESSION['session_version'] = (int)$user['session_version'];
        $_SESSION['user_id'] = (int)$user['id'];
        $orgs = fetchUserOrganizations($db, (int)$user['id']);
        if (!empty($orgs)) {
            $_SESSION['organization_id'] = (int)$orgs[0]['id'];
        }
        $redirect = safe_redirect($_GET['redirect'] ?? 'index.php');
        header('Location: ' . $redirect);
        exit;
    }

    $failures = ($attempt && (int)$attempt['last_attempt'] > time()-900) ? (int)$attempt['failures']+1 : 1;
    $db->prepare('INSERT INTO login_attempts(identifier,failures,last_attempt,blocked_until) VALUES (?,?,?,?) ON CONFLICT(identifier) DO UPDATE SET failures=excluded.failures,last_attempt=excluded.last_attempt,blocked_until=excluded.blocked_until')->execute([$identifier,$failures,time(),$failures >= 5 ? time()+900 : 0]);
    $db->prepare('DELETE FROM login_attempts WHERE last_attempt < ?')->execute([time()-86400]);
    $error = t('Ongeldige gebruikersnaam of wachtwoord.');
}
?>
<!DOCTYPE html>
<html lang="<?= app_language() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ht('Inloggen · FlowExpense') ?></title>
    <link rel="stylesheet" href="style.php">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .auth-card {
            width: 100%;
            max-width: 420px;
            padding: 36px;
            border-radius: 18px;
            background: var(--color-surface);
            box-shadow: var(--shadow-md);
        }
        .auth-card h1 {
            margin-top: 0;
            margin-bottom: 8px;
        }
        .auth-card p {
            margin-top: 0;
            color: var(--color-muted);
        }
        .error {
            margin-bottom: 18px;
            padding: 12px;
            border-radius: var(--radius-sm);
            background: rgba(239, 68, 68, 0.12);
            color: #b91c1c;
        }
        .auth-card form {
            box-shadow: none;
            padding: 0;
            background: transparent;
            margin-bottom: 0;
        }
        .auth-card label {
            color: #111827;
            font-weight: 500;
        }
        .auth-demo-option { border-top:1px solid var(--color-border);margin-top:24px;padding-top:20px;display:grid;gap:12px;text-align:center; }
        .auth-demo-option > span { font-size:.85rem;color:var(--color-muted); }
        .auth-demo-option .auth-guest-button { width:100%;background:#eff6ff;color:#2563eb; }
        .auth-demo-option a { display:block;background:#eff6ff;color:#2563eb; }
        .auth-demo-option p { font-size:.8rem;margin:0; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="sidebar-header" style="margin-bottom: 24px;">
            <div class="logo-circle"></div>
            <span class="brand">FlowExpense</span>
        </div>
        <?php language_selector(); ?>
        <h1><?= ht('Welkom terug') ?></h1>
        <p><?= ht('Log in met je bedrijfsaccount om je onkostennota\'s te beheren.') ?></p>
        <?php if (getenv('GUEST_ENABLED') === '1' && !$accountsReady): ?><p class="notice"><?= ht('Account access is not configured yet. You can already try the guest demo.') ?></p><?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="on"><?php csrf_field(); ?>
            <label><?= ht('Gebruikersnaam of e-mailadres') ?>
                <input type="text" name="email" autocomplete="username" required autofocus>
            </label>
            <label><?= ht('Wachtwoord') ?>
                <input type="password" name="password" required>
            </label>
            <button type="submit"><?= ht('Inloggen') ?></button>
        </form>
        <div class="auth-demo-option">
            <?php if (guest_available()): ?>
            <form method="post" action="guest.php"><?php csrf_field(); ?><button type="submit" class="auth-guest-button"><?= ht('Try as a guest') ?></button></form>
            <p><?= ht('Try the demo with fictional expenses. Your guest workspace is separate from registered accounts.') ?></p>
            <?php else: ?>
            <span><?= ht('Or explore FlowExpense') ?></span>
            <a class="button" href="https://alanlouette.be/flowexpense/" target="_blank" rel="noopener"><?= ht('Try as a guest') ?></a>
            <p><?= ht('Try the public demo with fictional expenses. Your personal account stays separate.') ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
