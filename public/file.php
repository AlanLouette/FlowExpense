<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/expenses.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit(t('Ongeldig ID.'));
}

$stmt = $db->prepare('SELECT a.original_name, a.stored_name, a.mime, a.size_bytes, r.organization_id, r.user_id FROM expense_attachments a INNER JOIN expense_reports r ON r.id = a.report_id WHERE a.id = ? AND a.deleted_at IS NULL AND r.deleted_at IS NULL');
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file || (int)$file['organization_id'] !== (int)$currentOrganization['id']) {
    http_response_code(404);
    exit(t('Bestand niet gevonden.'));
}

$isAdmin = $currentUser && (int)$currentUser['is_admin'] === 1;
if (!$isAdmin && (int)$file['user_id'] !== (int)$currentUser['id']) {
    http_response_code(403);
    exit(t('Geen toegang tot dit bestand.'));
}

$path = attachment_directory() . '/' . $file['stored_name'];
if (!is_file($path)) {
    http_response_code(404);
    exit(t('Bestand ontbreekt.'));
}

$mime = $file['mime'] ?: 'application/octet-stream';
$safeName = preg_replace('/[^A-Za-z0-9 _.\-()+]/', '_', $file['original_name']);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
