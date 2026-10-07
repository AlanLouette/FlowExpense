<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/expenses.php';
require_once __DIR__ . '/vendor/autoload.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM expense_reports WHERE id=? AND organization_id=? AND deleted_at IS NULL');
$stmt->execute([$id, (int)$currentOrganization['id']]); $report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report) { http_response_code(404); exit(t('Onkostennota niet gevonden.')); }
if (!(int)$currentUser['is_admin'] && (int)$report['user_id'] !== (int)$currentUser['id']) { http_response_code(403); exit(t('Geen toegang tot deze onkostennota.')); }
$stmt = $db->prepare('SELECT * FROM expense_lines WHERE report_id=? ORDER BY id');
$stmt->execute([$id]); $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
$book = new Spreadsheet(); $sheet = $book->getActiveSheet(); $sheet->setTitle(t('Onkostennota'));
$text = function(string $cell, $value) use ($sheet): void { $sheet->setCellValueExplicit($cell, (string)$value, DataType::TYPE_STRING); };
foreach ([1 => ['Reference', $report['custom_id']], 2 => ['Expense type', t($report['expense_type'] === 'business' ? 'Business expense' : 'Reimbursement')], 3 => ['Supplier', $report['supplier']], 4 => ['Recipient', $report['recipient']], 5 => ['Date', $report['date']], 6 => ['Description', $report['description']]] as $row => [$label, $value]) { $text("A$row", t($label)); $text("B$row", $value); }
$headers = ['Description','Quantity','Unit','Unit price excluding VAT','VAT rate (%)','Amount excluding VAT','VAT amount','Amount including VAT'];
foreach ($headers as $i => $label) $text(chr(65+$i).'8', t($label));
$row = 9;
foreach ($lines as $line) {
    $amounts = expense_line_amounts($line);
    $text("A$row", $line['description']); $text("C$row", $line['unit']);
    foreach (['B' => $line['quantity'], 'D' => $line['rate'], 'E' => $line['vat_rate'], 'F' => $amounts['net'], 'G' => $amounts['vat'], 'H' => $amounts['gross']] as $column => $value) $sheet->setCellValue("$column$row", (float)$value);
    $row++;
}
$text("E$row", t('Totaal'));
foreach (['F'=>'net_total','G'=>'vat_total','H'=>'total'] as $column=>$key) $sheet->setCellValue("$column$row", (float)$report[$key]);
$sheet->getStyle("D9:D$row")->getNumberFormat()->setFormatCode('0.0000');
$sheet->getStyle("F9:H$row")->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle('A8:H8')->getFont()->setBold(true);
$sheet->getStyle("E$row:H$row")->getFont()->setBold(true);
foreach (range('A','H') as $column) $sheet->getColumnDimension($column)->setWidth($column === 'A' ? 40 : 23);
$sheet->getStyle('A1:H'.$row)->getAlignment()->setWrapText(true);
$sheet->freezePane('A9');
$filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $report['custom_id'] ?: 'expense-'.$id);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'.xlsx"');
(new Xlsx($book))->save('php://output');
