<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/expenses.php';
requireLogin();
require_post();
enforceOrganizationAccess($currentOrganization ?? null);

$reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
if ($reportId <= 0) {
    http_response_code(400);
    exit(t('Ongeldig rapport.'));
}

$redirect = safe_redirect($_POST['redirect'] ?? ('form.php?id=' . $reportId));

$stmt = $db->prepare('SELECT organization_id, user_id FROM expense_reports WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$reportId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report || (int)$report['organization_id'] !== (int)$currentOrganization['id']) {
    http_response_code(404);
    exit(t('Onkostennota niet gevonden.'));
}

$isAdmin = $currentUser && (int)$currentUser['is_admin'] === 1;
if (!$isAdmin && (int)$report['user_id'] !== (int)$currentUser['id']) {
    http_response_code(403);
    exit(t('Geen toestemming om bijlagen toe te voegen.'));
}

$dir = attachment_directory();
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}

$allowedExt = ['pdf', 'csv', 'xlsx', 'xls', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
$failed = false;
$uploaded = 0;
$maxSize = 20 * 1024 * 1024;

if (!isset($_FILES['files'])) {
    header('Location: ' . $redirect);
    exit;
}

$files = $_FILES['files'];
$count = is_array($files['name']) ? count($files['name']) : 0;

for ($i = 0; $i < $count; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $failed = true;
        continue;
    }
    if ($files['size'][$i] > $maxSize) {
        $failed = true;
        continue;
    }

    $orig = $files['name'][$i];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $failed = true;
        continue;
    }

    $safeOrig = preg_replace('/[^A-Za-z0-9 _.\-()+]/', '_', $orig);
    $stored = sprintf('%d_%s_%s.%s', $reportId, date('YmdHis'), bin2hex(random_bytes(4)), $ext);
    $dest = rtrim($dir, '/') . '/' . $stored;

    if (!move_uploaded_file($files['tmp_name'][$i], $dest)) {
        $failed = true;
        continue;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $dest) ?: null;
    finfo_close($finfo);

    $mimeTypes = [
        'pdf' => ['application/pdf'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp']
    ];
    if (!in_array($mime, $mimeTypes[$ext], true)) {
        unlink($dest);
        $failed = true;
        continue;
    }

    $stmt = $db->prepare('INSERT INTO expense_attachments (report_id, original_name, stored_name, mime, size_bytes) VALUES (?, ?, ?, ?, ?)');
    try {
        $db->beginTransaction();
        $stmt->execute([$reportId, $safeOrig, $stored, $mime, filesize($dest)]);
        record_event('attachment_uploaded',$reportId,['name'=>$safeOrig]);
        $db->commit();
        $uploaded++;
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        unlink($dest);
        $failed = true;
    }
}

$_SESSION['upload_message'] = ($uploaded ? t('Attachments uploaded.') . ' ' : '') . ($failed ? t('Invalid file type or size (maximum 20 MB).') : '');
header('Location: ' . $redirect);
exit;
