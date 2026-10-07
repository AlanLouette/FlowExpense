<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/expenses.php';
require_once __DIR__ . '/lib/markdown.php';
require_once __DIR__ . '/layout.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);
$orgId = (int)$currentOrganization['id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$report = ['date' => date('Y-m-d'), 'expense_type' => $currentOrganization['usage_mode'] === 'solo' ? 'business' : 'reimbursement'];
$lines = [['quantity' => 1, 'unit' => '', 'vat_rate' => 0]];
if ($id !== null) {
    $stmt = $db->prepare('SELECT * FROM expense_reports WHERE id=? AND organization_id=? AND deleted_at IS NULL');
    $stmt->execute([$id, $orgId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) { http_response_code(404); exit(t('Onkostennota niet gevonden.')); }
    if (!(int)$currentUser['is_admin'] && (int)$report['user_id'] !== (int)$currentUser['id']) { http_response_code(403); exit(t('Geen toegang tot deze onkostennota.')); }
    $stmt = $db->prepare('SELECT * FROM expense_lines WHERE report_id=? ORDER BY id');
    $stmt->execute([$id]); $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$categoriesStmt = $db->prepare('SELECT * FROM expense_categories WHERE organization_id=? ORDER BY name');
$categoriesStmt->execute([$orgId]); $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
$recipientsStmt = $db->prepare('SELECT * FROM recipients WHERE organization_id=? ORDER BY name');
$recipientsStmt->execute([$orgId]); $recipients = $recipientsStmt->fetchAll(PDO::FETCH_ASSOC);
$unitsStmt = $db->prepare('SELECT name FROM units WHERE organization_id=? ORDER BY name');
$unitsStmt->execute([$orgId]); $units = $unitsStmt->fetchAll(PDO::FETCH_COLUMN);
$quick = $report['expense_type'] === 'business' && count($lines) === 1 && (float)($lines[0]['quantity'] ?? 1) === 1.0;
renderPageStart(t($id ? 'Edit expense' : 'New expense'), 'form');
function field_value(array $data, string $key, $default = ''): string { return htmlspecialchars((string)($data[$key] ?? $default), ENT_QUOTES); }
?>
<section class="card">
    <form action="save.php" method="post" id="reportForm"><?php csrf_field(); ?>
        <?php if ($id): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
        <div class="grid-2">
            <label><?= ht('Expense type') ?>
                <select name="expense_type" id="expenseType">
                    <option value="business" <?= $report['expense_type'] === 'business' ? 'selected' : '' ?>><?= ht('Business expense') ?></option>
                    <option value="reimbursement" <?= $report['expense_type'] === 'reimbursement' ? 'selected' : '' ?>><?= ht('Reimbursement') ?></option>
                </select>
            </label>
            <label><?= ht('Date') ?><input type="date" name="date" required value="<?= field_value($report, 'date') ?>"></label>
            <label><?= ht('Supplier') ?><input type="text" name="supplier" id="supplier" value="<?= field_value($report, 'supplier') ?>"></label>
            <label><?= ht('Category') ?>
                <select name="category_id"><option value=""><?= ht('None') ?></option>
                    <?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)($report['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['name']) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>
        <details <?= $report['expense_type'] === 'reimbursement' ? 'open' : '' ?> id="reimbursementDetails">
            <summary><?= ht('Reimbursement') ?></summary>
            <div class="grid-2" style="margin-top:16px">
                <label><?= ht('Ontvanger selecteren') ?><select id="recipientSelect"><option value=""><?= ht('-- Selecteer bestaande ontvanger --') ?></option>
                    <?php foreach ($recipients as $recipient): ?><option value="<?= htmlspecialchars(json_encode($recipient), ENT_QUOTES) ?>"><?= htmlspecialchars($recipient['name']) ?></option><?php endforeach; ?>
                </select></label>
                <?php foreach (['recipient' => 'Recipient', 'address' => 'Address', 'iban' => 'IBAN', 'bank_name' => 'Bank'] as $key => $label): ?>
                    <label><?= ht($label) ?><input type="text" name="<?= $key ?>" data-reimbursement value="<?= field_value($report, $key) ?>"></label>
                <?php endforeach; ?>
            </div>
        </details>
        <details style="margin:20px 0" <?= $id ? 'open' : '' ?>>
            <summary><?= ht('Notes') ?> / <?= ht('Nummer') ?></summary>
            <label><?= ht('Onkostennota nummer') ?><input type="text" name="custom_id" value="<?= field_value($report, 'custom_id') ?>" placeholder="<?= ht('Bijvoorbeeld EXP-0001') ?>"></label>
            <label><?= ht('Omschrijving / Doel (Markdown ondersteund)') ?><textarea name="description"><?= field_value($report, 'description') ?></textarea></label>
            <?php if (!empty($report['description'])): ?><div class="markdown-preview"><?= markdown_to_html($report['description']) ?></div><?php endif; ?>
        </details>
        <label class="quick-entry-control"><input type="checkbox" id="quickEntry" <?= $quick ? 'checked' : '' ?> style="width:auto"> <?= ht('Quick entry') ?></label>
        <p class="notice"><?= ht('VAT rates are entered manually; old reports retain their amounts with no recorded VAT.') ?></p>
        <div id="lines">
            <?php foreach ($lines as $line): ?>
                <div class="expense-line">
                    <div class="expense-fields">
                        <label><?= ht('Description') ?><textarea name="description_line[]" rows="2" required><?= field_value($line, 'description') ?></textarea></label>
                        <label class="line-detail"><?= ht('Quantity') ?><input type="number" name="quantity[]" min="0.0001" max="1000000" step="0.0001" required value="<?= field_value($line, 'quantity', 1) ?>"></label>
                        <label class="line-detail"><?= ht('Unit') ?><input type="text" name="unit[]" list="units" value="<?= field_value($line, 'unit') ?>"></label>
                        <label><span class="price-label"><?= ht('Unit price excluding VAT') ?></span><input type="number" name="rate[]" min="0" max="1000000" step="0.0001" required value="<?= field_value($line, 'rate') ?>"></label>
                        <label><?= ht('VAT rate (%)') ?><input type="number" name="vat_rate[]" min="0" max="100" step="0.01" required value="<?= field_value($line, 'vat_rate', 0) ?>"></label>
                    </div>
                    <button type="button" class="remove-line"><?= ht('Remove line') ?></button>
                </div>
            <?php endforeach; ?>
        </div>
        <datalist id="units"><?php foreach ($units as $unit): ?><option value="<?= htmlspecialchars($unit, ENT_QUOTES) ?>"><?php endforeach; ?></datalist>
        <button type="button" id="addLine"><?= ht('Add line') ?></button>
        <div class="expense-totals" aria-live="polite">
            <div><?= ht('Amount excluding VAT') ?>: <strong id="netTotal"></strong></div>
            <div><?= ht('VAT amount') ?>: <strong id="vatTotal"></strong></div>
            <div><?= ht('Amount including VAT') ?>: <strong id="grossTotal"></strong></div>
        </div>
        <button type="submit" class="expense-save"><?= ht('Save') ?></button>
        <?php if (!$id): ?><p class="notice"><?= ht('Save first to attach receipts.') ?></p><?php endif; ?>
    </form>
</section>
<?php if ($id):
    $att = $db->prepare('SELECT * FROM expense_attachments WHERE report_id=? AND deleted_at IS NULL ORDER BY uploaded_at DESC');
    $att->execute([$id]); $attachments = $att->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="card">
    <h2><?= ht('Attachments') ?></h2>
    <?php if (!empty($_SESSION['upload_message'])): ?><p class="notice"><?= htmlspecialchars($_SESSION['upload_message']) ?></p><?php unset($_SESSION['upload_message']); endif; ?>
    <?php if (!$attachments): ?><p><?= ht('No attachments yet.') ?></p><?php endif; ?>
    <?php foreach ($attachments as $attachment): ?>
        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;margin:12px 0">
            <span><?= htmlspecialchars($attachment['original_name']) ?></span>
            <a href="file.php?id=<?= (int)$attachment['id'] ?>" target="_blank" rel="noopener"><?= ht('View') ?></a>
            <form action="delete-attachment.php" method="post" onsubmit="return confirm(<?= htmlspecialchars(jt('Bijlage verwijderen?'), ENT_QUOTES) ?>)" style="margin:0;padding:0;box-shadow:none"><?php csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int)$attachment['id'] ?>"><input type="hidden" name="redirect" value="form.php?id=<?= $id ?>"><button type="submit"><?= ht('Delete') ?></button>
            </form>
        </div>
    <?php endforeach; ?>
    <form action="upload-attachment.php" method="post" enctype="multipart/form-data"><?php csrf_field(); ?>
        <input type="hidden" name="report_id" value="<?= $id ?>"><input type="hidden" name="redirect" value="form.php?id=<?= $id ?>">
        <label><?= ht('Bestanden uploaden') ?><input type="file" name="files[]" multiple accept=".pdf,.csv,.xlsx,.xls,.jpg,.jpeg,.png,.gif,.webp" required></label>
        <?php if (demo_mode()): ?><p class="notice"><?= ht('Demo uploads: fictional documents only, 2 MB per file, 5 MB per upload and 20 MB per workspace.') ?></p><?php endif; ?>
        <button type="submit"><?= ht('Upload') ?></button>
    </form>
    <form action="duplicate.php" method="post" class="inline-action"><?php csrf_field(); ?><input type="hidden" name="id" value="<?= $id ?>"><button type="submit"><?= ht('Duplicate') ?></button></form>
</section>
<?php endif; ?>
<script>
const form = document.getElementById('reportForm');
const lines = document.getElementById('lines');
const template = lines.querySelector('.expense-line').cloneNode(true);
const quick = document.getElementById('quickEntry');
const type = document.getElementById('expenseType');
const money = new Intl.NumberFormat(<?= json_encode(app_language()) ?>, {style:'currency',currency:'EUR'});
function refresh() {
    const business = type.value === 'business';
    document.getElementById('supplier').required = business;
    document.querySelectorAll('[data-reimbursement]').forEach(el => el.required = !business);
    document.getElementById('reimbursementDetails').hidden = business;
    if (!business) document.getElementById('reimbursementDetails').open = true;
    quick.disabled = !business || lines.children.length !== 1;
    if (quick.disabled) quick.checked = false;
    document.querySelectorAll('.line-detail').forEach(el => el.hidden = quick.checked);
    document.querySelectorAll('.price-label').forEach(el => el.textContent = quick.checked ? <?= jt('Amount excluding VAT') ?> : <?= jt('Unit price excluding VAT') ?>);
    document.getElementById('addLine').hidden = quick.checked;
    document.querySelectorAll('.remove-line').forEach(el => el.hidden = lines.children.length <= 1);
    let net = 0, vat = 0;
    lines.querySelectorAll('.expense-line').forEach(line => {
        line.classList.toggle('quick-line', quick.checked);
        const qty = Number(line.querySelector('[name="quantity[]"]').value) || 0;
        const rate = Number(line.querySelector('[name="rate[]"]').value) || 0;
        const tax = Number(line.querySelector('[name="vat_rate[]"]').value) || 0;
        const cents = Math.round(qty * rate * 100 + 1e-8);
        net += cents; vat += Math.round(cents * tax / 100 + 1e-8);
    });
    document.getElementById('netTotal').textContent = money.format(net/100);
    document.getElementById('vatTotal').textContent = money.format(vat/100);
    document.getElementById('grossTotal').textContent = money.format((net+vat)/100);
}
quick.addEventListener('change', () => {
    if (quick.checked) {
        const line = lines.firstElementChild;
        const qty = line.querySelector('[name="quantity[]"]');
        const rate = line.querySelector('[name="rate[]"]');
        // Converting to quick entry preserves the line's net amount.
        rate.value = (Number(qty.value) * Number(rate.value)).toFixed(2);
        qty.value = '1';
    }
    refresh();
});
document.getElementById('addLine').addEventListener('click', () => {
    const line = template.cloneNode(true);
    line.querySelectorAll('input, textarea').forEach(el => el.value = el.name === 'quantity[]' ? '1' : el.name === 'vat_rate[]' ? '0' : '');
    lines.appendChild(line); refresh();
});
lines.addEventListener('click', e => { if(e.target.classList.contains('remove-line') && lines.children.length > 1) { e.target.closest('.expense-line').remove(); refresh(); } });
form.addEventListener('input', refresh);
type.addEventListener('change', refresh);
document.getElementById('recipientSelect').addEventListener('change', e => {
    if (!e.target.value) return;
    const data = JSON.parse(e.target.value);
    for (const [name,key] of Object.entries({recipient:'name',address:'address',iban:'iban',bank_name:'bank_name'})) form.elements[name].value = data[key] || '';
});
refresh();
</script>
<?php renderPageEnd(); ?>
