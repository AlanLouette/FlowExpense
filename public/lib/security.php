<?php
function csrf_token(): string
{
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}
function verify_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $provided = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($provided) || !hash_equals(csrf_token(), $provided)) {
        http_response_code(403);
        exit(t('Your session has changed. Reload the page and try again.'));
    }
}
function safe_redirect(string $candidate, string $fallback = 'index.php'): string
{
    $parts = parse_url($candidate);
    if (!$parts || isset($parts['scheme']) || isset($parts['host']) || str_starts_with($candidate, '//') || preg_match('/[\x00-\x20\\\\]/', $candidate)) return $fallback;
    $path = $parts['path'] ?? '';
    if ($path === '/') return 'index.php';
    $pages = ['index.php','login.php','form.php','settings.php','users.php','organizations.php','categories.php','units.php','recipients.php','recipient-detail.php','trash.php','history.php','backups.php'];
    if (!in_array(ltrim($path, '/'), $pages, true)) return $fallback;
    return ltrim($path, '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
}
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit(t('Method not allowed')); }
}
function record_event(string $action, ?int $reportId = null, array $details = [], ?int $organizationId = null): void
{
    global $db, $currentUser, $currentOrganization;
    $db->prepare('INSERT INTO audit_events (organization_id,user_id,report_id,action,details) VALUES (?,?,?,?,?)')->execute([
        $organizationId ?? ($currentOrganization['id'] ?? null), $currentUser['id'] ?? null, $reportId, $action,
        json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    ]);
}
function accessible_report(int $id, bool $deleted = false): array
{
    global $db, $currentUser, $currentOrganization;
    $stmt = $db->prepare('SELECT * FROM expense_reports WHERE id=? AND organization_id=? AND deleted_at IS ' . ($deleted ? 'NOT NULL' : 'NULL'));
    $stmt->execute([$id, (int)$currentOrganization['id']]); $report = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) { http_response_code(404); exit(t('Onkostennota niet gevonden.')); }
    if (!(int)$currentUser['is_admin'] && (int)$report['user_id'] !== (int)$currentUser['id']) { http_response_code(403); exit(t('Geen toegang tot deze onkostennota.')); }
    return $report;
}
