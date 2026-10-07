<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/pdf.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit(t('Onkostennota niet gevonden.'));
}

$isAdmin = $currentUser && (int)$currentUser['is_admin'] === 1;

$stmt = $db->prepare('SELECT * FROM expense_reports WHERE id = ? AND organization_id = ? AND deleted_at IS NULL');
$stmt->execute([$id, (int)$currentOrganization['id']]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    http_response_code(404);
    exit(t('Onkostennota niet gevonden.'));
}

if (!$isAdmin && (int)$report['user_id'] !== (int)$currentUser['id']) {
    http_response_code(403);
    exit(t('Geen toegang tot deze onkostennota.'));
}

$linesStmt = $db->prepare('SELECT description, quantity, unit, rate, vat_rate FROM expense_lines WHERE report_id = ? ORDER BY id ASC');
$linesStmt->execute([$id]);
$lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

$html = expense_pdf_html($report, $lines, $currentOrganization);

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filenameSafe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $report['custom_id'] ?: ('nota_' . $report['id']));
$dompdf->stream($filenameSafe . '.pdf', ['Attachment' => false]);
exit;
