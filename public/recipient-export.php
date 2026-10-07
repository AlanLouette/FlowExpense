<?php
ob_start();
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/markdown.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$format = $_GET['format'] ?? 'xlsx';
if ($id <= 0) {
    ob_end_clean();
    http_response_code(400);
    exit(t('Ongeldig ID.'));
}

$orgId = (int)$currentOrganization['id'];
$isAdmin = $currentUser && (int)$currentUser['is_admin'] === 1;

$stmt = $db->prepare('SELECT * FROM recipients WHERE id = ? AND organization_id = ?');
$stmt->execute([$id, $orgId]);
$recipient = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$recipient) {
    ob_end_clean();
    http_response_code(404);
    exit(t('Ontvanger niet gevonden.'));
}

$query = 'SELECT r.*, c.name AS category_name FROM expense_reports r LEFT JOIN expense_categories c ON r.category_id = c.id WHERE r.organization_id = ? AND r.deleted_at IS NULL AND TRIM(r.recipient) = TRIM(?)';
$params = [$orgId, $recipient['name']];
if (!$isAdmin) {
    $query .= ' AND r.user_id = ?';
    $params[] = (int)$currentUser['id'];
}
$query .= ' ORDER BY r.date DESC';
$reportsStmt = $db->prepare($query);
$reportsStmt->execute($params);
$reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);

$grandTotal = 0.0;
foreach ($reports as $r) {
    $grandTotal += (float)($r['total'] ?? 0);
}

$recipientName = $recipient['name'] ?? '';
$recipientAddress = $recipient['address'] ?? '';
$recipientIban = $recipient['iban'] ?? '';
$recipientBank = $recipient['bank_name'] ?? '';

if ($format === 'pdf') {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="<?= app_language() ?>">
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
            h1 { font-size: 20px; margin-bottom: 6px; }
            .meta { margin-bottom: 12px; }
            .meta p { margin: 2px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #f1f5f9; padding: 8px; text-align: left; font-size: 11px; text-transform: uppercase; color: #64748b; }
            td { border-bottom: 1px solid #e2e8f0; padding: 8px; }
            tbody tr:nth-child(even) { background: #f8fafc; }
            .total { margin-top: 12px; font-weight: bold; }
        </style>
    </head>
    <body>
        <h1><?= ht('Overzicht onkostennota\'s') ?></h1>
        <div class="meta">
            <p><strong><?= ht('Ontvanger:') ?></strong> <?= htmlspecialchars($recipientName) ?></p>
            <p><strong><?= ht('Adres:') ?></strong> <?= htmlspecialchars($recipientAddress) ?></p>
            <p><strong>IBAN:</strong> <?= htmlspecialchars($recipientIban) ?></p>
            <p><strong><?= ht('Bank:') ?></strong> <?= htmlspecialchars($recipientBank) ?></p>
        </div>
        <table>
            <thead>
            <tr>
                <th><?= ht('Nummer') ?></th>
                <th><?= ht('Datum') ?></th>
                <th><?= ht('Omschrijving') ?></th>
                <th><?= ht('Categorie') ?></th>
                <th><?= ht('Bedrag') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?= htmlspecialchars($report['custom_id'] ?: '#' . $report['id']) ?></td>
                    <td><?= htmlspecialchars($report['date'] ?? '') ?></td>
                    <td><?= strip_tags(markdown_to_html($report['description'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($report['category_name'] ?? '-') ?></td>
                    <td>€ <?= localized_number((float)($report['total'] ?? 0), 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="total"><?= ht('Totaalbedrag: €') ?> <?= localized_number($grandTotal, 2, ',', '.') ?></div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    ob_end_clean();
    $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $recipientName);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename=overzicht-' . $safeName . '.pdf');
    echo $dompdf->output();
    exit;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(t('Overzicht'));
$sheet->setCellValueExplicit('A1', (string)(t('Onkostennota overzicht – ') . $recipientName), DataType::TYPE_STRING);
$sheet->setCellValueExplicit('A3', (string)(t('Adres:')), DataType::TYPE_STRING);
$sheet->setCellValueExplicit('B3', (string)($recipientAddress), DataType::TYPE_STRING);
$sheet->setCellValueExplicit('A4', (string)('IBAN:'), DataType::TYPE_STRING);
$sheet->setCellValueExplicit('B4', (string)($recipientIban), DataType::TYPE_STRING);
$sheet->setCellValueExplicit('A5', (string)(t('Bank:')), DataType::TYPE_STRING);
$sheet->setCellValueExplicit('B5', (string)($recipientBank), DataType::TYPE_STRING);
$sheet->setCellValue('A7', t('Nummer'));
$sheet->setCellValue('B7', t('Datum'));
$sheet->setCellValue('C7', t('Omschrijving'));
$sheet->setCellValue('D7', t('Categorie'));
$sheet->setCellValue('E7', t('Bedrag'));

$row = 8;
foreach ($reports as $report) {
    $sheet->setCellValueExplicit("A{$row}", (string)($report['custom_id'] ?? ('#' . $report['id'])), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("B{$row}", (string)($report['date'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("C{$row}", (string)(strip_tags(markdown_to_html($report['description'] ?? ''))), DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("D{$row}", (string)($report['category_name'] ?? ''), DataType::TYPE_STRING);
    $sheet->setCellValue("E{$row}", (float)($report['total'] ?? 0));
    $row++;
}
$sheet->setCellValueExplicit("D{$row}", (string)(t('Totaal')), DataType::TYPE_STRING);
$sheet->setCellValue("E{$row}", $grandTotal);

$safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $recipientName);
ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename=overzicht-' . $safeName . '.xlsx');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
