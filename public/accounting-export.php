<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/expenses.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);
$where = ['r.organization_id = ?', 'r.deleted_at IS NULL'];
$params = [(int)$currentOrganization['id']];
if (!(int)$currentUser['is_admin']) { $where[] = 'r.user_id = ?'; $params[] = (int)$currentUser['id']; }
foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
    $value = $_GET[$key] ?? '';
    if ($value === '') continue;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) { http_response_code(400); exit(t('Invalid date range.')); }
    $where[] = "r.date $operator ?"; $params[] = $value;
}
if (!empty($_GET['from']) && !empty($_GET['to']) && $_GET['from'] > $_GET['to']) { http_response_code(400); exit(t('Invalid date range.')); }
$type = $_GET['expense_type'] ?? '';
if ($type !== '') {
    if (!in_array($type, ['business', 'reimbursement'], true)) { http_response_code(400); exit(t('Invalid expense type.')); }
    $where[] = 'r.expense_type = ?'; $params[] = $type;
}
$stmt = $db->prepare('SELECT r.*, c.name AS category_name, u.name AS owner FROM expense_reports r LEFT JOIN expense_categories c ON c.id=r.category_id LEFT JOIN users u ON u.id=r.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.date,r.id');
$stmt->execute($params); $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$reports) { http_response_code(404); exit(t('No expenses to export.')); }
$temp = tempnam(sys_get_temp_dir(), 'flowexpense-accounting-');
$zip = new ZipArchive();
$opened = false;
try {
    if ($zip->open($temp, ZipArchive::OVERWRITE) !== true) throw new RuntimeException(t('Kon ZIP-bestand niet openen.'));
    $opened = true;
    $csv = fopen('php://temp', 'w+');
    fwrite($csv, "\xEF\xBB\xBF");
    $write = function(array $row) use ($csv): void {
        // Escape formula-like text when CSV is opened in spreadsheet applications.
        foreach ($row as &$value) if (is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value)) $value = "'" . $value;
        fputcsv($csv, $row, ';', '"', '');
    };
    $write(array_map('t', ['Reference','Date','Expense type','Supplier','Recipient','Owner','Category','Description','Amount excluding VAT','VAT amount','Amount including VAT','Status','Receipt paths']));
    $lineCsv = fopen('php://temp', 'w+');
    fwrite($lineCsv, "\xEF\xBB\xBF");
    fputcsv($lineCsv, array_map('t', ['Reference','Line description','Quantity','Unit','Unit price excluding VAT','VAT rate (%)','Amount excluding VAT','VAT amount','Amount including VAT']), ';', '"', '');
    $attachments = $db->prepare('SELECT * FROM expense_attachments WHERE report_id=? AND deleted_at IS NULL ORDER BY id');
    $lines = $db->prepare('SELECT * FROM expense_lines WHERE report_id=? ORDER BY id');
    foreach ($reports as $report) {
        $attachments->execute([$report['id']]); $paths = [];
        foreach ($attachments->fetchAll(PDO::FETCH_ASSOC) as $attachment) {
            $path = attachment_directory() . '/' . basename($attachment['stored_name']);
            if (!is_file($path)) throw new RuntimeException(t('Receipt files missing. No archive was generated.'));
            $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($attachment['original_name']));
            $archivePath = 'receipts/' . $report['id'] . '/' . $attachment['id'] . '-' . $safeName;
            if (!$zip->addFile($path, $archivePath)) throw new RuntimeException(t('Export mislukt: '));
            $paths[] = $archivePath;
        }
        $write([$report['custom_id'], $report['date'], t($report['expense_type'] === 'business' ? 'Business expense' : 'Reimbursement'), $report['supplier'], $report['recipient'], $report['owner'], $report['category_name'], $report['description'], number_format((float)$report['net_total'], 2, '.', ''), number_format((float)$report['vat_total'], 2, '.', ''), number_format((float)$report['total'], 2, '.', ''), t($report['status']), implode(' | ', $paths)]);
        $lines->execute([$report['id']]);
        foreach ($lines->fetchAll(PDO::FETCH_ASSOC) as $line) {
            $amounts = expense_line_amounts($line);
            $values = [$report['custom_id'], $line['description'], $line['quantity'], $line['unit'], $line['rate'], $line['vat_rate'], $amounts['net'], $amounts['vat'], $amounts['gross']];
            foreach ($values as &$value) if (is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value)) $value = "'" . $value;
            unset($value);
            fputcsv($lineCsv, $values, ';', '"', '');
        }
    }
    rewind($csv); rewind($lineCsv);
    $zip->addFromString('expenses.csv', stream_get_contents($csv));
    $zip->addFromString('expense-lines.csv', stream_get_contents($lineCsv));
    fclose($csv); fclose($lineCsv);
    if (!$zip->close()) throw new RuntimeException(t('Export mislukt: '));
    $opened = false;
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="accounting-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($temp));
    readfile($temp);
} catch (Throwable $error) {
    if ($opened) $zip->close();
    http_response_code(500);
    echo htmlspecialchars($error->getMessage(), ENT_QUOTES);
} finally { if (is_file($temp)) unlink($temp); }
