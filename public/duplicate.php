<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/expenses.php';
requireLogin();
require_post();
enforceOrganizationAccess($currentOrganization ?? null);

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit(t('Ongeldig ID.'));
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

try {
    $db->beginTransaction();

    $newCustomId = generate_custom_id($db, (int)$currentOrganization['id']);
    $insert = $db->prepare('INSERT INTO expense_reports (organization_id, user_id, custom_id, recipient, address, description, iban, bank_name, date, total, status, category_id, expense_type, supplier, net_total, vat_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([
        (int)$currentOrganization['id'],
        (int)$currentUser['id'],
        $newCustomId,
        $report['recipient'],
        $report['address'],
        $report['description'],
        $report['iban'],
        $report['bank_name'],
        $report['date'],
        $report['total'],
        'Open',
        $report['category_id'],
        $report['expense_type'],
        $report['supplier'],
        $report['net_total'],
        $report['vat_total']
    ]);

    $newReportId = (int)$db->lastInsertId();

    $stmtLines = $db->prepare('SELECT description, quantity, unit, rate, vat_rate FROM expense_lines WHERE report_id = ?');
    $stmtLines->execute([$id]);
    $lines = $stmtLines->fetchAll(PDO::FETCH_ASSOC);

    if ($lines) {
        $insertLine = $db->prepare('INSERT INTO expense_lines (report_id, description, quantity, unit, rate, vat_rate) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($lines as $line) {
            $insertLine->execute([
                $newReportId,
                $line['description'],
                $line['quantity'],
                $line['unit'],
                $line['rate'],
                $line['vat_rate']
            ]);
        }
    }

    record_event('expense_duplicated',$newReportId,['source_id'=>$id,'reference'=>$newCustomId]);
    $db->commit();
    header('Location: form.php?id=' . $newReportId);
    exit;
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    exit(t('Dupliceren mislukt: ') . htmlspecialchars($e->getMessage()));
}
