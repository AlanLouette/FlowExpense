<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/pdf.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(t('Method not allowed'));
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || empty($payload['ids']) || !is_array($payload['ids'])) {
    http_response_code(400);
    exit(t('Ongeldige selectie.'));
}

$ids = array_unique(array_map('intval', $payload['ids']));
$ids = array_filter($ids, fn($id) => $id > 0);
if (empty($ids)) {
    http_response_code(400);
    exit(t('Geen geldige onkostennota IDs.'));
}

$isAdmin = $currentUser && (int)$currentUser['is_admin'] === 1;
$orgId = (int)$currentOrganization['id'];

$zip = new ZipArchive();
$tmpFile = tempnam(sys_get_temp_dir(), 'bulk-expenses');
if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit(t('Kon ZIP-bestand niet openen.'));
}

$reportStmt = $db->prepare('SELECT * FROM expense_reports WHERE id = ? AND organization_id = ? AND deleted_at IS NULL');
$linesStmt = $db->prepare('SELECT description, quantity, unit, rate, vat_rate FROM expense_lines WHERE report_id = ? ORDER BY id ASC');

foreach ($ids as $reportId) {
    $reportStmt->execute([$reportId, $orgId]);
    $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) {
        continue;
    }
    if (!$isAdmin && (int)$report['user_id'] !== (int)$currentUser['id']) {
        continue;
    }

    $linesStmt->execute([$reportId]);
    $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

    $dompdf = new Dompdf();
    $html = expense_pdf_html($report, $lines, $currentOrganization);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdfData = $dompdf->output();

    $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $report['custom_id'] ?: ('nota_' . $report['id'])) . '.pdf';
    $zip->addFromString($filename, $pdfData);
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="onkostennotas.zip"');
header('Content-Length: ' . filesize($tmpFile));
readfile($tmpFile);
@unlink($tmpFile);
exit;
