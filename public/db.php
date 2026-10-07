<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ && PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$dbPath = getenv('EXPENSE_DB_PATH') ?: (is_dir('/var/www/data') ? '/var/www/data/expenses.db' : __DIR__ . '/../data/expenses.db');
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys = ON');

function columnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare("PRAGMA table_info($table)");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $info) {
        if (strcasecmp($info['name'], $column) === 0) {
            return true;
        }
    }
    return false;
}

$db->exec("CREATE TABLE IF NOT EXISTS organizations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    legal_name TEXT,
    address_line1 TEXT,
    address_line2 TEXT,
    postal_code TEXT,
    city TEXT,
    country TEXT,
    vat_number TEXT,
    iban TEXT,
    bank_name TEXT,
    contact_email TEXT,
    phone TEXT,
    branding_primary TEXT DEFAULT '#1f2933',
    branding_accent TEXT DEFAULT '#3b82f6',
    number_prefix TEXT DEFAULT 'EXP',
    next_expense_number INTEGER DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    is_admin INTEGER DEFAULT 0,
    active INTEGER DEFAULT 1,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS user_organizations (
    user_id INTEGER NOT NULL,
    organization_id INTEGER NOT NULL,
    role TEXT DEFAULT 'member',
    PRIMARY KEY (user_id, organization_id),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS recipients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    name TEXT,
    address TEXT,
    iban TEXT,
    bank_name TEXT,
    FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS expense_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS units (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    UNIQUE(organization_id, name),
    FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS expense_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER NOT NULL,
    user_id INTEGER,
    custom_id TEXT,
    recipient TEXT,
    address TEXT,
    description TEXT,
    iban TEXT,
    bank_name TEXT,
    date TEXT,
    total REAL,
    status TEXT DEFAULT 'Open',
    category_id INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY(organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY(category_id) REFERENCES expense_categories(id) ON DELETE SET NULL
)");

$db->exec("CREATE TABLE IF NOT EXISTS expense_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER,
    description TEXT,
    quantity REAL,
    unit TEXT,
    rate REAL,
    FOREIGN KEY(report_id) REFERENCES expense_reports(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS expense_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    original_name TEXT NOT NULL,
    stored_name TEXT NOT NULL,
    mime TEXT,
    size_bytes INTEGER,
    uploaded_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(report_id) REFERENCES expense_reports(id) ON DELETE CASCADE
)");

if (!columnExists($db, 'recipients', 'organization_id')) {
    $db->exec('ALTER TABLE recipients ADD COLUMN organization_id INTEGER');
}
if (!columnExists($db, 'expense_categories', 'organization_id')) {
    $db->exec('ALTER TABLE expense_categories ADD COLUMN organization_id INTEGER');
}
if (!columnExists($db, 'units', 'organization_id')) {
    $db->exec('ALTER TABLE units ADD COLUMN organization_id INTEGER');
}
if (!columnExists($db, 'expense_reports', 'organization_id')) {
    $db->exec('ALTER TABLE expense_reports ADD COLUMN organization_id INTEGER');
}
if (!columnExists($db, 'expense_reports', 'user_id')) {
    $db->exec('ALTER TABLE expense_reports ADD COLUMN user_id INTEGER');
}
if (!columnExists($db, 'expense_reports', 'status')) {
    $db->exec("ALTER TABLE expense_reports ADD COLUMN status TEXT DEFAULT 'Open'");
}
if (!columnExists($db, 'expense_reports', 'category_id')) {
    $db->exec('ALTER TABLE expense_reports ADD COLUMN category_id INTEGER');
}
if (!columnExists($db, 'organizations', 'branding_primary')) {
    $db->exec("ALTER TABLE organizations ADD COLUMN branding_primary TEXT DEFAULT '#1f2933'");
}
if (!columnExists($db, 'organizations', 'branding_accent')) {
    $db->exec("ALTER TABLE organizations ADD COLUMN branding_accent TEXT DEFAULT '#3b82f6'");
}
if (!columnExists($db, 'organizations', 'number_prefix')) {
    $db->exec("ALTER TABLE organizations ADD COLUMN number_prefix TEXT DEFAULT 'EXP'");
}
if (!columnExists($db, 'organizations', 'next_expense_number')) {
    $db->exec('ALTER TABLE organizations ADD COLUMN next_expense_number INTEGER DEFAULT 1');
}

// Additive migrations preserve existing reports; their previous amounts have no recorded VAT.
$db->beginTransaction();
try {
    foreach ([
        ['organizations', 'usage_mode', "TEXT NOT NULL DEFAULT 'team'"],
        ['expense_reports', 'deleted_at', 'TEXT'],
        ['expense_attachments', 'deleted_at', 'TEXT'],
        ['users', 'session_version', 'INTEGER NOT NULL DEFAULT 1'],
        ['expense_reports', 'expense_type', "TEXT NOT NULL DEFAULT 'reimbursement'"],
        ['expense_reports', 'supplier', "TEXT NOT NULL DEFAULT ''"],
        ['expense_reports', 'net_total', 'REAL NOT NULL DEFAULT 0'],
        ['expense_reports', 'vat_total', 'REAL NOT NULL DEFAULT 0'],
        ['expense_lines', 'vat_rate', 'REAL NOT NULL DEFAULT 0']
    ] as [$table, $column, $definition]) {
        if (!columnExists($db, $table, $column)) {
            $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            if ($column === 'net_total') {
                $db->exec('UPDATE expense_reports SET net_total = COALESCE(total, 0)');
            }
        }
    }
    $db->commit();
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}

$db->exec("CREATE TABLE IF NOT EXISTS audit_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    organization_id INTEGER, user_id INTEGER, report_id INTEGER,
    action TEXT NOT NULL, details TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$db->exec('CREATE INDEX IF NOT EXISTS audit_org_time ON audit_events(organization_id,created_at)');
$db->exec('CREATE INDEX IF NOT EXISTS expense_org_deleted_date ON expense_reports(organization_id,deleted_at,date)');
$db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
    identifier TEXT PRIMARY KEY, failures INTEGER NOT NULL DEFAULT 0,
    last_attempt INTEGER NOT NULL, blocked_until INTEGER NOT NULL DEFAULT 0
)");
$db->exec('PRAGMA busy_timeout = 5000');
