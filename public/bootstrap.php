<?php

if (is_file(__DIR__ . '/hosting-config.php')) require_once __DIR__ . '/hosting-config.php';
loadDotEnv(getenv('FLOWEXPENSE_ENV_FILE') ?: __DIR__ . '/../.env');
require_once __DIR__ . '/lib/demo.php';
if (getenv('APP_ENV') === 'production' && ((!demo_mode() && !getenv('EXPENSE_DB_PATH')) || getenv('APP_HTTPS') !== '1')) { http_response_code(503); exit('Configure private storage and HTTPS before production use.'); }
umask(0077);
$appTimezone = getenv('APP_TIMEZONE') ?: 'Europe/Brussels';
if (in_array($appTimezone, DateTimeZone::listIdentifiers(), true)) date_default_timezone_set($appTimezone);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    if (getenv('FLOWEXPENSE_DEMO') === '1') session_name('FlowExpenseDemo');
    elseif (getenv('GUEST_ENABLED') === '1') session_name('FlowExpense');
    $cookiePath = guest_available() ? rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/' : '/';
    session_set_cookie_params(['path' => $cookiePath, 'httponly' => true, 'samesite' => 'Lax', 'secure' => getenv('APP_HTTPS') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
    session_start();
}
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 7200) {
    unset($_SESSION['user_id'], $_SESSION['organization_id'], $_SESSION['session_version']);
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();
demo_prepare();
$storagePath = getenv('EXPENSE_DB_PATH') ?: (is_dir('/var/www/data') ? '/var/www/data/expenses.db' : __DIR__ . '/../data/expenses.db');
if (!is_dir(dirname($storagePath))) mkdir(dirname($storagePath), 0700, true);
$storageLock = $GLOBALS['demoStorageLock'] ?? fopen(dirname($storagePath) . '/application.lock', 'c');
if (!$storageLock || !flock($storageLock, LOCK_EX)) throw new RuntimeException('Application storage unavailable');
register_shutdown_function(function() use ($storageLock) { flock($storageLock, LOCK_UN); fclose($storageLock); });
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/security.php';
verify_csrf();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; object-src 'none'; form-action 'self'");
header('Cache-Control: no-store');

function loadDotEnv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $rawLine) {
        $line = trim($rawLine);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        if (stripos($line, 'export ') === 0) {
            $line = trim(substr($line, 7));
        }

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if ($name === '') {
            continue;
        }

        if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        $value = str_replace(['\n', '\r'], ["\n", "\r"], $value);

        if (getenv($name) !== false) {
            continue;
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}


function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return ($value === false) ? $default : $value;
}

function ensureDefaultOrganization(PDO $db): int
{
    $existing = $db->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }

    if (demo_mode()) {
        $db->prepare('INSERT INTO organizations(name,legal_name) VALUES(?,?)')->execute([t('Guest workspace'),t('Fictional demonstration organization')]);
        return (int)$db->lastInsertId();
    }
    $name = env('DEFAULT_ORG_NAME', 'Main Organization');
    $legal = env('DEFAULT_ORG_LEGAL_NAME', $name);
    $address1 = env('DEFAULT_ORG_ADDRESS_LINE1', 'Straatnaam 1');
    $address2 = env('DEFAULT_ORG_ADDRESS_LINE2', '');
    $postal = env('DEFAULT_ORG_POSTAL', '1000');
    $city = env('DEFAULT_ORG_CITY', 'Brussel');
    $country = env('DEFAULT_ORG_COUNTRY', 'België');
    $vat = env('DEFAULT_ORG_VAT', 'BE0000000000');
    $iban = env('DEFAULT_ORG_IBAN', 'BE00 0000 0000 0000');
    $bank = env('DEFAULT_ORG_BANK', 'FinBank');
    $primary = env('DEFAULT_ORG_COLOR_PRIMARY', '#1f2933');
    $accent = env('DEFAULT_ORG_COLOR_ACCENT', '#3b82f6');
    $prefix = env('DEFAULT_ORG_NUMBER_PREFIX', 'EXP');
    $next = (int)env('DEFAULT_ORG_NEXT_NUMBER', '1');

    $stmt = $db->prepare('INSERT INTO organizations (name, legal_name, address_line1, address_line2, postal_code, city, country, vat_number, iban, bank_name, branding_primary, branding_accent, number_prefix, next_expense_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $name,
        $legal,
        $address1,
        $address2,
        $postal,
        $city,
        $country,
        $vat,
        $iban,
        $bank,
        $primary,
        $accent,
        $prefix,
        $next
    ]);

    return (int)$db->lastInsertId();
}

function ensureAdminUser(PDO $db, int $organizationId): array
{
    $existing = $db->query('SELECT * FROM users WHERE is_admin=1 ORDER BY active DESC,id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        // Never reset a password or re-enable a disabled account during ordinary requests.
        // Retire the legacy public default account only if another active admin is available.
        $legacy = $db->query("SELECT * FROM users WHERE email='admin@example.com' AND active=1")->fetch(PDO::FETCH_ASSOC);
        $other = $db->query("SELECT COUNT(*) FROM users WHERE is_admin=1 AND active=1 AND email<>'admin@example.com'")->fetchColumn();
        if ($legacy && $other && password_verify('changeme123', $legacy['password_hash'])) {
            $db->prepare('UPDATE users SET active=0,session_version=session_version+1 WHERE id=?')->execute([$legacy['id']]);
        }
        return $existing;
    }
    $email = env('ADMIN_EMAIL', '');
    $password = env('ADMIN_PASSWORD', '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || $password === 'changeme123') {
        if (getenv('GUEST_ENABLED') === '1') return []; // Guest entry remains available before private account setup.
        http_response_code(503);
        exit(t('Configure an administrator email and a password of at least 12 characters before first use.'));
    }
    $db->prepare('INSERT INTO users(name,email,password_hash,is_admin,active) VALUES (?,?,?,1,1)')->execute([env('ADMIN_NAME','Administrator'),$email,password_hash($password,PASSWORD_DEFAULT)]);
    $id = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO user_organizations(user_id,organization_id,role) VALUES (?,?,'admin')")->execute([$id,$organizationId]);
    $stmt=$db->prepare('SELECT * FROM users WHERE id=?');$stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function backfillOrganizationData(PDO $db, int $organizationId): void
{
    $db->exec('UPDATE recipients SET organization_id = ' . (int)$organizationId . ' WHERE organization_id IS NULL');
    $db->exec('UPDATE expense_categories SET organization_id = ' . (int)$organizationId . ' WHERE organization_id IS NULL');
    $db->exec('UPDATE units SET organization_id = ' . (int)$organizationId . ' WHERE organization_id IS NULL');
    $db->exec('UPDATE expense_reports SET organization_id = ' . (int)$organizationId . ' WHERE organization_id IS NULL');
}

$defaultOrgId = ensureDefaultOrganization($db);
if (demo_mode()) demo_seed($db, $defaultOrgId);
$defaultAdmin = demo_mode() ? null : ensureAdminUser($db, $defaultOrgId);
demo_check_limits($db);
backfillOrganizationData($db, $defaultOrgId);

if (!empty($defaultAdmin) && $db->query('SELECT COUNT(*) FROM expense_reports WHERE user_id IS NULL')->fetchColumn() > 0) {
    $update = $db->prepare('UPDATE expense_reports SET user_id = ? WHERE user_id IS NULL');
    $update->execute([$defaultAdmin['id']]);
}

$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$currentUser || (int)($_SESSION['session_version'] ?? 0) !== (int)$currentUser['session_version']) {
        $currentUser = null;
        unset($_SESSION['user_id']);
    }
}

function requireLogin(bool $redirect = true): void
{
    global $currentUser;
    if ($currentUser) {
        return;
    }

    if ($redirect) {
        $target = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header('Location: login.php?redirect=' . $target);
        exit;
    }

    http_response_code(401);
    exit(t('Authentication required.'));
}

function requireAdmin(): void
{
    global $currentUser;
    requireLogin();
    if (!$currentUser || (int)$currentUser['is_admin'] !== 1) {
        http_response_code(403);
        exit(t('Admin privileges required.'));
    }
}

function fetchUserOrganizations(PDO $db, int $userId): array
{
    $stmt = $db->prepare('SELECT o.*, uo.role FROM organizations o INNER JOIN user_organizations uo ON uo.organization_id = o.id WHERE uo.user_id = ? ORDER BY o.name');
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$currentOrganization = null;
$availableOrganizations = [];
if ($currentUser) {
    $availableOrganizations = fetchUserOrganizations($db, (int)$currentUser['id']);
    $requestedOrgId = $_SESSION['organization_id'] ?? null;

    foreach ($availableOrganizations as $org) {
        if ($requestedOrgId && (int)$requestedOrgId === (int)$org['id']) {
            $currentOrganization = $org;
            break;
        }
    }

    if (!$currentOrganization && !empty($availableOrganizations)) {
        $currentOrganization = $availableOrganizations[0];
        $_SESSION['organization_id'] = (int)$currentOrganization['id'];
    }
}

function enforceOrganizationAccess(?array $organization): void
{
    if (!$organization) {
        requireLogin();
        http_response_code(403);
        exit(t('No organization selected.'));
    }
}
require_once __DIR__ . '/lib/backup.php';
if (PHP_SAPI !== 'cli' && getenv('BACKUP_AUTOMATIC') !== '0') maybe_daily_backup();
