<?php
require_once __DIR__ . '/bootstrap.php';

$primary = '#1f2933';
$accent = '#3b82f6';
$organization = null;
if (isset($_SESSION['organization_id'])) {
    $stmt = $db->prepare('SELECT branding_primary, branding_accent FROM organizations WHERE id = ?');
    $stmt->execute([(int)$_SESSION['organization_id']]);
    $organization = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($organization) {
        $primary = $organization['branding_primary'] ?: $primary;
        $accent = $organization['branding_accent'] ?: $accent;
    }
}

if (!preg_match('/^#[0-9a-f]{6}$/i', $primary)) $primary='#1f2933';
if (!preg_match('/^#[0-9a-f]{6}$/i', $accent)) $accent='#3b82f6';
header('Content-Type: text/css');
?>
:root {
    --color-primary: <?= $primary ?>;
    --color-accent: <?= $accent ?>;
    --color-surface: #ffffff;
    --color-muted: #6b7280;
    --color-border: #e5e7eb;
    --color-background: #f4f6fb;
    --radius-lg: 16px;
    --radius-md: 12px;
    --radius-sm: 8px;
    --shadow-sm: 0 8px 16px rgba(15, 23, 42, 0.06);
    --shadow-md: 0 18px 30px rgba(15, 23, 42, 0.08);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: var(--color-background);
    color: #1f2937;
    font-size: 16px;
    line-height: 1.6;
}

a {
    color: var(--color-accent);
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

.app-shell {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr);
    min-height: 100vh;
}

.app-sidebar {
    background: var(--color-surface);
    border-right: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    padding: 28px 24px;
}

.sidebar-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 28px;
}

.logo-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
    box-shadow: inset 0 0 0 2px rgba(255,255,255,0.2);
}

.brand {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--color-primary);
    letter-spacing: 0.02em;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex: 1;
}

.nav-link {
    padding: 10px 14px;
    border-radius: var(--radius-sm);
    color: #111827;
    font-weight: 500;
    transition: background 0.2s ease, color 0.2s ease;
}

.nav-link:hover {
    background: rgba(59, 130, 246, 0.08);
}

.nav-link.active {
    background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
    color: #fff;
    box-shadow: var(--shadow-sm);
}

.sidebar-footer {
    border-top: 1px solid var(--color-border);
    padding-top: 20px;
    margin-top: 30px;
}

.user-meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 12px;
}

.user-name {
    font-weight: 600;
    color: var(--color-primary);
}

.user-email {
    font-size: 0.85rem;
    color: var(--color-muted);
}

.logout-link {
    font-size: 0.9rem;
    color: var(--color-muted);
}

.app-content {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.app-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 32px 40px 16px;
}

.app-topbar h1 {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 600;
}

.org-subtitle {
    display: block;
    font-size: 0.9rem;
    color: var(--color-muted);
    margin-top: 4px;
}

.org-switcher {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: flex-start;
    font-size: 0.85rem;
    color: var(--color-muted);
    background: transparent;
    padding: 0;
    margin: 0;
    border-radius: 0;
    box-shadow: none;
}

.org-switcher select {
    padding: 10px 12px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
    background: #f9fafb;
    color: #111827;
    font-size: 1rem;
}

.app-main {
    padding: 0 40px 40px;
}

.card {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
}

button,
input[type="submit"],
.button {
    background: linear-gradient(135deg, var(--color-accent), var(--color-primary));
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    padding: 10px 18px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

button:hover,
input[type="submit"]:hover,
.button:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

button:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    box-shadow: none;
}

form {
    display: grid;
    gap: 18px;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    padding: 24px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 28px;
}

.grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
}

.table-actions form {
    background: transparent;
    padding: 0;
    margin: 0;
    border-radius: 0;
    box-shadow: none;
    vertical-align: middle;
}

form .grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
}

label {
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 0.9rem;
    color: var(--color-muted);
}

input,
textarea,
select {
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    padding: 10px 12px;
    font-size: 1rem;
    background: #f9fafb;
    color: #111827;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

input:focus,
textarea:focus,
select:focus {
    outline: none;
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

textarea {
    min-height: 120px;
    resize: vertical;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
}

th,
td {
    padding: 14px 18px;
    text-align: left;
    border-bottom: 1px solid var(--color-border);
}

th {
    background: rgba(17, 24, 39, 0.03);
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--color-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

tbody tr:nth-child(even) {
    background: rgba(59, 130, 246, 0.04);
}

tbody tr:hover {
    background: rgba(59, 130, 246, 0.08);
}

.status-select {
    border-radius: var(--radius-sm);
    padding: 6px 10px;
    font-size: 0.9rem;
    border: 1px solid transparent;
    background: rgba(59, 130, 246, 0.1);
    color: var(--color-primary);
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 500;
    background: rgba(59, 130, 246, 0.12);
    color: var(--color-accent);
}

.table-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.table-actions a,
.table-actions button {
    background: none;
    border: none;
    color: var(--color-accent);
    padding: 0;
    cursor: pointer;
}

.table-actions button:hover,
.table-actions a:hover {
    text-decoration: underline;
}

.bulk-toolbar {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 18px;
}

.notice {
    padding: 16px;
    border-radius: var(--radius-md);
    background: rgba(59, 130, 246, 0.12);
    color: var(--color-primary);
    box-shadow: var(--shadow-sm);
}

.markdown-preview {
    border: 1px dashed var(--color-border);
    border-radius: var(--radius-sm);
    padding: 16px;
    background: #f9fafb;
    min-height: 120px;
}

.markdown-preview h1,
.markdown-preview h2,
.markdown-preview h3 {
    margin-top: 1.2em;
}

.markdown-preview ul,
.markdown-preview ol {
    padding-left: 1.2rem;
}

.markdown-preview code {
    background: rgba(15, 23, 42, 0.08);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'JetBrains Mono', monospace;
}

@media (max-width: 1024px) {
    .app-shell {
        grid-template-columns: 1fr;
    }
    .app-sidebar {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-right: none;
        border-bottom: 1px solid var(--color-border);
    }
    .sidebar-nav {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 8px;
    }
    .app-topbar {
        padding: 24px 20px 12px;
    }
    .app-main {
        padding: 0 20px 20px;
    }
}

/* Ensure conditional form fields stay hidden even when label styles set display. */
[hidden] { display: none !important; }
.topbar-right { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
.language-switcher select { min-width: 120px; }
@media (max-width: 640px) {
    .grid-2 { grid-template-columns: minmax(0, 1fr); }
    .app-topbar { align-items: flex-start; flex-wrap: wrap; gap: 16px; }
    .app-main { overflow-x: auto; }
}

.inline-action { display: inline; padding: 0; margin: 0; background: transparent; box-shadow: none; border-radius: 0; }
.inline-action .logout-link { background: none; box-shadow: none; padding: 0; font-weight: normal; }
.table-actions .danger-link { color: #dc2626; }

/* User administration: keep related controls together without nested cards. */
.users-page { max-width: 1200px; margin: 0 auto; }
.users-page h2 { margin: 0 0 24px; }
.users-page .user-form { padding: 0; margin: 0; background: transparent; box-shadow: none; border-radius: 0; gap: 16px; }
.user-create-fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
.user-create-fields small { font-size: .78rem; }
.user-create-options { display: flex; align-items: center; gap: 24px; padding-top: 18px; border-top: 1px solid var(--color-border); }
.user-create-options > button { margin-left: auto; }
.user-organizations { min-width: 0; border: 0; padding: 0; margin: 0; }
.user-organizations legend { font-size: .9rem; color: var(--color-muted); padding: 0; margin-bottom: 10px; }
.user-checks { display: flex; flex-wrap: wrap; gap: 8px 16px; }
.users-page .user-check { display: flex; flex-direction: row; align-items: center; gap: 8px; color: var(--color-primary); }
.users-page input[type="checkbox"] { width: 16px; height: 16px; margin: 0; padding: 0; flex-shrink: 0; accent-color: var(--color-accent); }
.user-entry { padding: 24px 0; border-top: 1px solid var(--color-border); }
.user-entry:first-child { padding-top: 0; border-top: 0; }
.user-entry:last-child { padding-bottom: 0; }
.user-overview { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.user-identity { display: grid; gap: 5px; min-width: 0; }
.user-identity strong { font-size: 1rem; }
.user-identity span { color: var(--color-muted); font-size: .9rem; overflow-wrap: anywhere; }
.user-labels { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.user-status { font-size: .8rem; color: var(--color-muted); }
.user-status.is-active { color: #15803d; }
.user-management { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 32px; }
.users-page .user-membership { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; align-self: start; }
.user-account-actions { display: grid; gap: 14px; }
.users-page .user-reset { gap: 8px; }
.user-reset-controls { display: flex; gap: 8px; }
.user-reset-controls input { min-width: 0; width: 100%; }
.users-page .user-secondary { background: #f5f7fb; border: 1px solid var(--color-border); color: var(--color-primary); white-space: nowrap; }
.user-toggle-actions { display: flex; gap: 20px; flex-wrap: wrap; }
.users-page .user-text-action { background: none; padding: 0; color: var(--color-accent); font-size: .85rem; box-shadow: none; }
.users-page .user-text-action:hover { transform: none; text-decoration: underline; }
.users-page button:disabled { opacity: .4; }
@media (max-width: 1000px) {
    .user-create-fields, .user-management { grid-template-columns: minmax(0, 1fr); }
    .user-create-options { flex-wrap: wrap; }
    .user-management { gap: 20px; }
}
@media (max-width: 600px) {
    .user-overview { align-items: flex-start; flex-direction: column; }
    .user-create-options { align-items: flex-start; flex-direction: column; gap: 16px; }
    .user-create-options > button { margin-left: 0; width: 100%; }
    .users-page .user-membership { flex-wrap: wrap; }
    .user-reset-controls { flex-wrap: wrap; }
}
@media (max-width: 640px) {
    .app-shell { min-width: 0; }
    .app-sidebar { flex-direction: column; align-items: stretch; gap: 12px; min-width: 0; }
    .sidebar-header { margin-bottom: 0; }
    .sidebar-nav { flex-wrap: nowrap; overflow-x: auto; flex: none; padding-bottom: 6px; }
    .sidebar-nav .nav-link { white-space: nowrap; flex-shrink: 0; }
    .sidebar-footer { margin-top: 0; }
    .users-page .card { padding: 20px; }
}

.dashboard-overview { display: flex; align-items: center; gap: 36px; padding: 18px 24px; }
.dashboard-overview > div:first-child { flex: 1; }
.dashboard-overview h2 { margin: 0 0 4px; font-size: 1.1rem; }
.dashboard-caption, .dashboard-total > span { font-size: .8rem; color: var(--color-muted); }
.dashboard-total { display: grid; gap: 2px; }
.dashboard-total strong { font-size: 1.4rem; white-space: nowrap; }
.dashboard-filters { padding: 18px 24px; margin-bottom: 12px; }
.dashboard-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px 16px; }
.dashboard-filter-grid label { gap: 5px; font-size: .8rem; }
.dashboard-filter-grid input, .dashboard-filter-grid select { min-width: 0; padding: 8px 10px; font-size: .85rem; }
.dashboard-filter-grid .bulk-toolbar { grid-column: 1 / -1; margin: 0; gap: 12px; }
.dashboard-filter-grid button { padding: 8px 14px; font-size: .85rem; }
.dashboard-filter-grid .dashboard-reset { background: none; color: var(--color-muted); box-shadow: none; }
.dashboard-export { margin: 0 0 16px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-surface); }
.dashboard-export summary { cursor: pointer; padding: 12px 18px; color: var(--color-accent); font-weight: 600; font-size: .9rem; }
.dashboard-export summary:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px; }
.dashboard-export .dashboard-export-form { background: none; box-shadow: none; border-radius: 0; padding: 8px 18px 18px; margin: 0; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.dashboard-export-form button { grid-column: 1 / -1; justify-self: start; }
@media (min-width: 1600px) { .dashboard-filter-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); } }
@media (max-width: 640px) {
    .dashboard-overview { flex-wrap: wrap; gap: 16px 24px; }
    .dashboard-overview > div:first-child { flex-basis: 100%; }
    .dashboard-filter-grid { grid-template-columns: minmax(0, 1fr); }
    .dashboard-export .dashboard-export-form { grid-template-columns: minmax(0, 1fr); }
}

.dashboard-table-scroll { overflow-x: auto; margin-bottom: 24px; }
.dashboard-table-scroll table { margin-bottom: 0; }

.demo-notice { display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 20px;margin-bottom:20px;border:1px solid #bfdbfe;border-radius:12px;background:#eff6ff;font-size:.85rem; }
.demo-notice p { margin:4px 0 0;color:#64748b; }
.demo-notice button { white-space:nowrap; }
@media(max-width:640px){.demo-notice{flex-direction:column;align-items:flex-start;}}

.nav-group { display:flex;flex-direction:column;gap:4px;margin-bottom:14px; }
.nav-group-title { font-size:.68rem;letter-spacing:.08em;text-transform:uppercase;color:var(--color-muted);font-weight:600;padding:4px 14px 8px; }
.nav-locked { display:flex;align-items:center;justify-content:space-between;gap:12px;color:#8b95a5;background:#f8fafc;cursor:not-allowed;font-size:.9rem; }
.nav-locked:hover { background:#f8fafc;text-decoration:none; }
.nav-locked:focus-visible { outline:2px solid var(--color-accent);outline-offset:2px; }
.nav-locked > span:last-child { font-size:.75rem;opacity:.65; }
@media(max-width:1024px){ .nav-group { margin:0; } .sidebar-nav { align-items:flex-start; } }
@media(max-width:640px){ .nav-group { flex-direction:row;align-items:center;flex-shrink:0;gap:4px; } .nav-group-title { padding:0 8px;font-size:.6rem; } }

/* Align expense amounts on one row and keep actions compact. */
#reportForm .expense-line { border:1px solid var(--color-border);background:#f8fafc;border-radius:12px;padding:16px;margin:12px 0;box-shadow:none; }
#reportForm .expense-fields { display:grid;grid-template-columns:minmax(180px,2fr) minmax(80px,.65fr) minmax(80px,.8fr) minmax(130px,1fr) minmax(90px,.65fr);gap:14px;align-items:start; }
#reportForm .expense-line.quick-line .expense-fields { grid-template-columns:minmax(180px,2fr) minmax(130px,1fr) minmax(90px,.7fr); }
#reportForm .expense-fields label { min-width:0;font-size:.82rem; }
#reportForm .expense-fields input, #reportForm .expense-fields textarea { width:100%;min-width:0;padding:10px 12px;font-size:.9rem; }
#reportForm .expense-fields textarea { height:44px;min-height:44px;resize:vertical; }
#reportForm .remove-line { background:none;color:#dc2626;padding:6px 0;margin-top:12px;font-size:.8rem;box-shadow:none; }
#reportForm #addLine { justify-self:start;background:#eff6ff;color:var(--color-accent);padding:9px 14px; }
#reportForm .expense-save { justify-self:end;min-width:140px; }
#reportForm .expense-totals { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;border-top:1px solid var(--color-border);padding-top:20px;margin:8px 0;color:var(--color-muted);font-size:.85rem; }
#reportForm .expense-totals strong { display:block;color:var(--color-primary);font-size:1.2rem;margin-top:4px; }
@media(max-width:1200px){
    #reportForm .expense-fields { grid-template-columns:repeat(4,minmax(0,1fr)); }
    #reportForm .expense-fields > label:first-child { grid-column:1/-1; }
    #reportForm .expense-line.quick-line .expense-fields { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
@media(max-width:600px){
    #reportForm .expense-fields { grid-template-columns:repeat(2,minmax(0,1fr)); }
    #reportForm .expense-totals { grid-template-columns:1fr;gap:12px; }
    #reportForm .expense-save { width:100%; }
}

#reportForm .expense-fields { align-items:end; }
#reportForm .expense-fields input { height:44px; }
#reportForm .quick-entry-control { flex-direction:row;align-items:center;gap:10px;justify-self:start; }
#reportForm > .grid-2 { grid-template-columns:repeat(4,minmax(0,1fr)); }
@media(max-width:1200px){ #reportForm > .grid-2 { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:600px){ #reportForm > .grid-2 { grid-template-columns:1fr; } }
