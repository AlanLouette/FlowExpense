<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/expenses.php';
requireLogin();
require_post();
enforceOrganizationAccess($currentOrganization ?? null);

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$redirect = safe_redirect($_POST['redirect'] ?? 'index.php');

if ($id <= 0) {
    http_response_code(400);
    exit(t('Ongeldig ID.'));
}

$stmt = $db->prepare('SELECT a.stored_name, a.report_id, r.organization_id, r.user_id FROM expense_attachments a INNER JOIN expense_reports r ON r.id = a.report_id WHERE a.id = ? AND a.deleted_at IS NULL AND r.deleted_at IS NULL');
$stmt->execute([$id]);
$attachment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attachment || (int)$attachment['organization_id'] !== (int)$currentOrganization['id']) {
    http_response_code(404);
    exit(t('Bijlage niet gevonden.'));
}

$isAdmin = $currentUser && (int)$currentUser['is_admin'] === 1;
if (!$isAdmin && (int)$attachment['user_id'] !== (int)$currentUser['id']) {
    http_response_code(403);
    exit(t('Geen toestemming om deze bijlage te verwijderen.'));
}

$db->beginTransaction();
$db->prepare('UPDATE expense_attachments SET deleted_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);
record_event('attachment_deleted',(int)$attachment['report_id'],['attachment_id'=>$id]);
$db->commit();
header('Location: ' . $redirect);
exit;
