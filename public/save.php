<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/expenses.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(t('Method not allowed')); }
$orgId = (int)$currentOrganization['id'];
$isAdmin = (int)$currentUser['is_admin'] === 1;
try {
    $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $type = $_POST['expense_type'] ?? 'reimbursement';
    if (!in_array($type, ['business', 'reimbursement'], true)) throw new InvalidArgumentException(t('Invalid expense type.'));
    $date = trim($_POST['date'] ?? '');
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) throw new InvalidArgumentException(t('Ongeldige datum opgegeven.'));
    $supplier = trim($_POST['supplier'] ?? '');
    $recipient = trim($_POST['recipient'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $iban = trim($_POST['iban'] ?? '');
    $bank = trim($_POST['bank_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($type === 'business' && $supplier === '') throw new InvalidArgumentException(t('Enter a supplier.'));
    if ($type === 'reimbursement' && ($recipient === '' || $address === '' || $iban === '' || $bank === '')) throw new InvalidArgumentException(t('Verplichte velden ontbreken.'));
    $categoryId = ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null;
    if ($categoryId !== null) {
        $check = $db->prepare('SELECT id FROM expense_categories WHERE id = ? AND organization_id = ?');
        $check->execute([$categoryId, $orgId]);
        if (!$check->fetchColumn()) throw new InvalidArgumentException(t('Select a valid category.'));
    }
    foreach (['description_line','quantity','unit','rate','vat_rate'] as $field) {
        if (isset($_POST[$field]) && !is_array($_POST[$field])) throw new InvalidArgumentException(t('Invalid payload'));
    }
    $descriptions = $_POST['description_line'] ?? [];
    $lines = [];
    foreach ($descriptions as $index => $label) {
        $label = trim($label);
        if ($label === '') throw new InvalidArgumentException(t('Elke regel heeft een omschrijving nodig.'));
        $quantity = expense_decimal($_POST['quantity'][$index] ?? '');
        $rate = expense_decimal($_POST['rate'][$index] ?? '');
        $vatRate = expense_decimal($_POST['vat_rate'][$index] ?? '0');
        if ($quantity <= 0 || $vatRate > 100) throw new InvalidArgumentException(t('Amounts must be positive and VAT rates between 0 and 100.'));
        $lines[] = ['description' => $label, 'quantity' => $quantity, 'unit' => trim($_POST['unit'][$index] ?? ''), 'rate' => $rate, 'vat_rate' => $vatRate];
    }
    if (!$lines) throw new InvalidArgumentException(t('Voeg minstens één regel toe.'));
    $totals = expense_totals($lines);
    if ($totals['gross'] > 1000000000) throw new InvalidArgumentException(t('Invalid amount.'));
    $db->beginTransaction();
    $existing = null;
    if ($id !== null) {
        $stmt = $db->prepare('SELECT * FROM expense_reports WHERE id = ? AND organization_id = ? AND deleted_at IS NULL');
        $stmt->execute([$id, $orgId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) throw new RuntimeException(t('Onkostennota niet gevonden.'));
        if (!$isAdmin && (int)$existing['user_id'] !== (int)$currentUser['id']) {
            http_response_code(403);
            throw new RuntimeException(t('Geen toestemming om deze onkostennota te bewerken.'));
        }
    }
    $customId = trim($_POST['custom_id'] ?? '') ?: ($existing['custom_id'] ?? '');
    if ($customId === '') $customId = generate_custom_id($db, $orgId);
    else ensure_unique_custom_id($db, $orgId, $customId, $id);
    // Store the supplier separately; business expenses do not need bank or recipient details.
    $values = [$customId, $recipient, $address, $description, $iban, $bank, $date, $totals['gross'], $categoryId, $type, $supplier, $totals['net'], $totals['vat']];
    if ($existing) {
        $db->prepare('UPDATE expense_reports SET custom_id=?, recipient=?, address=?, description=?, iban=?, bank_name=?, date=?, total=?, category_id=?, expense_type=?, supplier=?, net_total=?, vat_total=? WHERE id=?')->execute([...$values, $id]);
        $db->prepare('DELETE FROM expense_lines WHERE report_id=?')->execute([$id]);
    } else {
        $db->prepare('INSERT INTO expense_reports (custom_id,recipient,address,description,iban,bank_name,date,total,category_id,expense_type,supplier,net_total,vat_total,organization_id,user_id,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([...$values, $orgId, (int)$currentUser['id'], 'Open']);
        $id = (int)$db->lastInsertId();
    }
    $insert = $db->prepare('INSERT INTO expense_lines (report_id,description,quantity,unit,rate,vat_rate) VALUES (?,?,?,?,?,?)');
    foreach ($lines as $line) $insert->execute([$id, $line['description'], $line['quantity'], $line['unit'], $line['rate'], $line['vat_rate']]);
    record_event($existing ? 'expense_updated' : 'expense_created',$id,['reference'=>$customId,'before'=>$existing ? array_intersect_key($existing,array_flip(['expense_type','supplier','description','date','net_total','vat_total','total','category_id'])) : null,'after'=>['expense_type'=>$type,'supplier'=>$supplier,'description'=>$description,'date'=>$date,'net_total'=>$totals['net'],'vat_total'=>$totals['vat'],'total'=>$totals['gross'],'category_id'=>$categoryId],'lines'=>$lines]);
    $db->commit();
    header('Location: form.php?id=' . $id);
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    if (http_response_code() !== 403) http_response_code(400);
    echo htmlspecialchars(t('Opslaan mislukt: ') . $error->getMessage(), ENT_QUOTES);
}
