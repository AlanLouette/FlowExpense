<?php
require_once __DIR__ . '/bootstrap.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);
require_once __DIR__ . '/lib/markdown.php';
require_once __DIR__ . '/layout.php';

$orgId = (int)$currentOrganization['id'];
$isAdmin = $currentUser && (int)$currentUser['is_admin'] === 1;

$categoriesStmt = $db->prepare('SELECT id, name FROM expense_categories WHERE organization_id = ? ORDER BY name ASC');
$categoriesStmt->execute([$orgId]);
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$recipientQuery = 'SELECT DISTINCT recipient FROM expense_reports WHERE organization_id = ? AND deleted_at IS NULL';
$recipientParams = [$orgId];
if (!$isAdmin) { $recipientQuery .= ' AND user_id = ?'; $recipientParams[] = (int)$currentUser['id']; }
$recipientsStmt = $db->prepare($recipientQuery . ' ORDER BY recipient ASC');
$recipientsStmt->execute($recipientParams);
$recipients = $recipientsStmt->fetchAll(PDO::FETCH_COLUMN);

$selectedCategoryId = $_GET['category_id'] ?? '';
$selectedRecipient = $_GET['recipient'] ?? '';
$selectedType = $_GET['expense_type'] ?? '';
$search = mb_substr(trim($_GET['q'] ?? ''),0,200);
$selectedMonth = $_GET['month'] ?? '';
if ($selectedMonth !== '' && (!preg_match('/^\d{4}-\d{2}$/D',$selectedMonth) || substr($selectedMonth,5,2)<'01' || substr($selectedMonth,5,2)>'12')) { http_response_code(400);exit(t('Invalid date range.')); }
$selectedStatus = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'date';
$order = strtoupper($_GET['order'] ?? 'DESC');

$allowedSort = ['custom_id', 'recipient', 'date', 'total', 'status'];
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'date';
}
if (!in_array($order, ['ASC', 'DESC'], true)) {
    $order = 'DESC';
}

$where = ['organization_id = ?', 'deleted_at IS NULL'];
$params = [$orgId];
if (!$isAdmin) {
    $where[] = 'user_id = ?';
    $params[] = (int)$currentUser['id'];
}
if (in_array($selectedType, ['business', 'reimbursement'], true)) {
    $where[] = 'expense_type = ?';
    $params[] = $selectedType;
}
if ($selectedCategoryId !== '') {
    $where[] = 'category_id = ?';
    $params[] = (int)$selectedCategoryId;
}
if ($selectedRecipient !== '') {
    $where[] = 'recipient = ?';
    $params[] = $selectedRecipient;
}
if ($selectedStatus !== '') {
    $where[] = 'status = ?';
    $params[] = $selectedStatus;
}

if ($search !== '') {
    $pattern='%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $search) . '%';
    $where[] = "(custom_id LIKE ? ESCAPE '\\' OR supplier LIKE ? ESCAPE '\\' OR recipient LIKE ? ESCAPE '\\' OR description LIKE ? ESCAPE '\\')";
    array_push($params,$pattern,$pattern,$pattern,$pattern);
}
if ($selectedMonth !== '') { $where[]='substr(date,1,7)=?';$params[]=$selectedMonth; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sortExpression=$sort === 'recipient' ? "CASE WHEN expense_type='business' THEN supplier ELSE recipient END" : $sort;
$query = "SELECT * FROM expense_reports $whereSql ORDER BY $sortExpression $order";
$stmt = $db->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$attCounts = [];
if ($reports) {
    $ids = array_column($reports, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $attStmt = $db->prepare("SELECT report_id, COUNT(*) as cnt FROM expense_attachments WHERE deleted_at IS NULL AND report_id IN ($placeholders) GROUP BY report_id");
    $attStmt->execute($ids);
    foreach ($attStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $attCounts[(int)$row['report_id']] = (int)$row['cnt'];
    }
}

$totalAmount = 0.0;
$totalOpen = 0.0;
foreach ($reports as $report) {
    $totalAmount += (float)($report['total'] ?? 0);
    if (($report['status'] ?? 'Open') === 'Open') {
        $totalOpen += (float)($report['total'] ?? 0);
    }
}

$summaryMonth=$selectedMonth ?: date('Y-m');
$summaryWhere=$where;$summaryParams=$params;
$summaryWhere[]='substr(date,1,7)=?';$summaryParams[]=$summaryMonth;
$summaryStmt=$db->prepare('SELECT * FROM expense_reports WHERE '.implode(' AND ',$summaryWhere));
$summaryStmt->execute($summaryParams);$monthlyReports=$summaryStmt->fetchAll(PDO::FETCH_ASSOC);
$categoryNames=array_column($categories,'name','id');
$byCategory=[];$bySupplier=[];$monthTotals=['net'=>0,'vat'=>0,'gross'=>0];
foreach ($monthlyReports as $row) {
    $category=$categoryNames[$row['category_id']] ?? t('Geen categorie');
    $supplier=$row['expense_type']==='business' ? $row['supplier'] : $row['recipient'];
    foreach (['byCategory'=>$category,'bySupplier'=>$supplier ?: '-'] as $target=>$label) {
        ${$target}[$label] ??= ['count'=>0,'total'=>0];
        ${$target}[$label]['count']++;${$target}[$label]['total'] += (float)$row['total'];
    }
    $monthTotals['net'] += (float)$row['net_total'];$monthTotals['vat'] += (float)$row['vat_total'];$monthTotals['gross'] += (float)$row['total'];
}
uasort($byCategory,fn($a,$b)=>$b['total']<=>$a['total']);uasort($bySupplier,fn($a,$b)=>$b['total']<=>$a['total']);
renderPageStart(t('Dashboard'), 'index');
?>
<section class="card dashboard-overview">
    <div><h2><?= ht('Overzicht') ?></h2><span class="dashboard-caption"><?= htmlspecialchars($currentOrganization['name']) ?> · <?= ht('Totals include VAT.') ?></span></div>
    <div class="dashboard-total"><span><?= ht('Totaal bedrag') ?></span><strong>€ <?= localized_number($totalAmount, 2, ',', '.') ?></strong></div>
    <div class="dashboard-total"><span><?= ht('Openstaand') ?></span><strong>€ <?= localized_number($totalOpen, 2, ',', '.') ?></strong></div>
</section>

<section class="card dashboard-filters">
    <form class="dashboard-filter-grid" method="get" id="filterForm" style="margin:0;box-shadow:none;background:transparent;padding:0;">
        <label><?= ht('Search expenses') ?><input type="search" name="q" value="<?= htmlspecialchars($search,ENT_QUOTES) ?>" placeholder="<?= ht('Supplier or recipient') ?>"></label>
        <label><?= ht('Month') ?><input type="month" name="month" value="<?= htmlspecialchars($selectedMonth,ENT_QUOTES) ?>"></label>
        <label><?= ht('Categorie') ?>
            <select name="category_id" onchange="this.form.submit()">
                <option value=""><?= ht('Alle categorieën') ?></option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= ((string)$selectedCategoryId === (string)$cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= ht('Ontvanger') ?>
            <select name="recipient" onchange="this.form.submit()">
                <option value=""><?= ht('Alle ontvangers') ?></option>
                <?php foreach ($recipients as $recipientName): if (trim((string)$recipientName) === '') continue; ?>
                    <option value="<?= htmlspecialchars($recipientName) ?>" <?= ($selectedRecipient === $recipientName) ? 'selected' : '' ?>><?= htmlspecialchars($recipientName) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= ht('Status') ?>
            <select name="status" onchange="this.form.submit()">
                <option value=""><?= ht('Alle statussen') ?></option>
                <option value="Open" <?= $selectedStatus === 'Open' ? 'selected' : '' ?>><?= ht('Open') ?></option>
                <option value="Complete" <?= $selectedStatus === 'Complete' ? 'selected' : '' ?>><?= ht('Complete') ?></option>
                <option value="Paid" <?= $selectedStatus === 'Paid' ? 'selected' : '' ?>><?= ht('Paid') ?></option>
            </select>
        </label>
        <label><?= ht('Expense type') ?>
            <select name="expense_type" onchange="this.form.submit()">
                <option value=""><?= ht('All expense types') ?></option>
                <option value="business" <?= $selectedType === 'business' ? 'selected' : '' ?>><?= ht('Business expense') ?></option>
                <option value="reimbursement" <?= $selectedType === 'reimbursement' ? 'selected' : '' ?>><?= ht('Reimbursement') ?></option>
            </select>
        </label>
        <div class="bulk-toolbar">
            <button type="submit"><?= ht('Search') ?></button>
            <button type="button" class="dashboard-reset" onclick="window.location.href='index.php'"><?= ht('Reset filters') ?></button>
        </div>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
        <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
    </form>
</section>

<details class="dashboard-export">
    <summary><?= ht('Accounting export') ?></summary>
    <form action="accounting-export.php" method="get" class="dashboard-export-form">
        <label><?= ht('From') ?><input type="date" name="from"></label>
        <label><?= ht('To') ?><input type="date" name="to"></label>
        <label><?= ht('Expense type') ?><select name="expense_type"><option value=""><?= ht('All expense types') ?></option><option value="business"><?= ht('Business expense') ?></option><option value="reimbursement"><?= ht('Reimbursement') ?></option></select></label>
        <button type="submit"><?= ht('Download CSV and receipts (ZIP)') ?></button>
    </form>
</details>
<div class="bulk-toolbar">
    <div><input type="checkbox" id="select-all"> <label for="select-all"><?= ht('Selecteer alles') ?></label></div>
    <button type="button" id="bulk-export" disabled><?= ht('Exporteer selectie (PDF)') ?></button>
    <button type="button" id="bulk-delete" disabled class="button" style="background:linear-gradient(135deg,#ef4444,#dc2626);"><?= ht('Verwijder selectie') ?></button>
    <span id="selection-count" class="badge" style="display:none;"></span>
</div>

<?php if (empty($reports)): ?>
    <div class="notice"><?= ht('Er zijn geen onkostennota\'s gevonden met de huidige filters.') ?></div>
<?php else: ?>
    <div class="dashboard-table-scroll"><table class="index-table">
        <thead>
        <tr>
            <th><input type="checkbox" id="select-all-head"></th>
            <th><a href="<?= sortUrl('custom_id', $sort, $order, $selectedCategoryId, $selectedRecipient, $selectedStatus) ?>"><?= ht('Nummer') ?></a></th>
            <th><a href="<?= sortUrl('recipient', $sort, $order, $selectedCategoryId, $selectedRecipient, $selectedStatus) ?>"><?= ht('Supplier or recipient') ?></a></th>
            <th><?= ht('Omschrijving') ?></th>
            <th><a href="<?= sortUrl('date', $sort, $order, $selectedCategoryId, $selectedRecipient, $selectedStatus) ?>"><?= ht('Datum') ?></a></th>
            <th><a href="<?= sortUrl('total', $sort, $order, $selectedCategoryId, $selectedRecipient, $selectedStatus) ?>"><?= ht('Totaal') ?></a></th>
            <th><a href="<?= sortUrl('status', $sort, $order, $selectedCategoryId, $selectedRecipient, $selectedStatus) ?>"><?= ht('Status') ?></a></th>
            <th><?= ht('Bijlagen') ?></th>
            <th><?= ht('Acties') ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($reports as $report): ?>
            <?php
            $desc = $report['description'] ?? '';
            $rendered = markdown_to_html($desc);
            $snippet = strip_tags($rendered);
            if (strlen($snippet) > 160) {
                $snippet = mb_substr($snippet, 0, 160) . '…';
            }
            $status = $report['status'] ?? 'Open';
            $attCnt = $attCounts[(int)$report['id']] ?? 0;
            ?>
            <tr data-id="<?= (int)$report['id'] ?>">
                <td><input type="checkbox" class="row-select" value="<?= (int)$report['id'] ?>"></td>
                <td><?= htmlspecialchars($report['custom_id'] ?: ('#' . $report['id'])) ?></td>
                <td><?= htmlspecialchars($report['expense_type'] === 'business' ? $report['supplier'] : ($report['recipient'] ?? '')) ?>
                    <div class="badge"><?= ht($report['expense_type'] === 'business' ? 'Business expense' : 'Reimbursement') ?></div></td>
                <td>
                    <div style="max-width:320px;">
                        <strong><?= htmlspecialchars($report['description'] ? strtok($report['description'], "\n") : t('Geen omschrijving')) ?></strong>
                        <div style="font-size:0.85rem;color:var(--color-muted);margin-top:4px;">
                            <?= htmlspecialchars($snippet) ?>
                        </div>
                    </div>
                </td>
                <td><?= htmlspecialchars($report['date'] ?? '') ?></td>
                <td>€ <?= localized_number((float)$report['total'], 2, ',', '.') ?></td>
                <td>
                    <select class="status-select" data-id="<?= (int)$report['id'] ?>">
                        <option value="Open" <?= $status === 'Open' ? 'selected' : '' ?>><?= ht('Open') ?></option>
                        <option value="Complete" <?= $status === 'Complete' ? 'selected' : '' ?>><?= ht('Complete') ?></option>
                        <option value="Paid" <?= $status === 'Paid' ? 'selected' : '' ?>><?= ht('Paid') ?></option>
                    </select>
                </td>
                <td><span class="badge">📎 <?= $attCnt ?></span></td>
                <td class="table-actions">
                    <a href="form.php?id=<?= (int)$report['id'] ?>"><?= ht('Bewerken') ?></a>
                    <a href="export.php?id=<?= (int)$report['id'] ?>" target="_blank" rel="noopener">PDF</a>
                    <a href="export-xlsx.php?id=<?= (int)$report['id'] ?>">Excel</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
<?php endif; ?>

<section class="card">
    <h2><?= ht('Monthly overview') ?> · <?= htmlspecialchars($summaryMonth) ?></h2>
    <div class="grid-2"><p><?= ht('Amount excluding VAT') ?>: <strong>€ <?= localized_number($monthTotals['net'],2) ?></strong></p><p><?= ht('VAT amount') ?>: <strong>€ <?= localized_number($monthTotals['vat'],2) ?></strong></p><p><?= ht('Amount including VAT') ?>: <strong>€ <?= localized_number($monthTotals['gross'],2) ?></strong></p></div>
    <?php if (!$monthlyReports): ?><p><?= ht('No expenses this month.') ?></p><?php else: ?>
    <div class="grid-2">
        <?php foreach (['By category'=>$byCategory,'By supplier or recipient'=>$bySupplier] as $heading=>$groups): ?><div><h3><?= ht($heading) ?></h3><table><thead><tr><th><?= ht('Naam') ?></th><th><?= ht('Count') ?></th><th><?= ht('Amount including VAT') ?></th></tr></thead><tbody><?php foreach ($groups as $label=>$group): ?><tr><td><?= htmlspecialchars((string)$label) ?></td><td><?= $group['count'] ?></td><td>€ <?= localized_number($group['total'],2) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<script>
const rowCheckboxes = Array.from(document.querySelectorAll('.row-select'));
const selectAll = document.getElementById('select-all');
const selectAllHead = document.getElementById('select-all-head');
const selectionCount = document.getElementById('selection-count');
const bulkExport = document.getElementById('bulk-export');
const bulkDelete = document.getElementById('bulk-delete');

function updateSelectionState() {
    const selected = rowCheckboxes.filter(cb => cb.checked).map(cb => cb.value);
    const hasSelection = selected.length > 0;
    bulkExport.disabled = !hasSelection;
    bulkDelete.disabled = !hasSelection;
    if (hasSelection) {
        selectionCount.textContent = selected.length + <?= jt(' geselecteerd') ?>;
        selectionCount.style.display = 'inline-flex';
    } else {
        selectionCount.style.display = 'none';
    }
    if (selectAll) {
        selectAll.checked = selected.length === rowCheckboxes.length && rowCheckboxes.length > 0;
    }
    if (selectAllHead) {
        selectAllHead.checked = selectAll.checked;
    }
}

function gatherSelection() {
    return rowCheckboxes.filter(cb => cb.checked).map(cb => cb.value);
}

function toggleAll(checked) {
    rowCheckboxes.forEach(cb => cb.checked = checked);
    updateSelectionState();
}

rowCheckboxes.forEach(cb => cb.addEventListener('change', updateSelectionState));
if (selectAll) {
    selectAll.addEventListener('change', (e) => toggleAll(e.target.checked));
}
if (selectAllHead) {
    selectAllHead.addEventListener('change', (e) => toggleAll(e.target.checked));
}

async function handleBulkExport() {
    const ids = gatherSelection();
    if (!ids.length) return;
    const res = await fetch('bulk-export.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': <?= json_encode(csrf_token()) ?> },
        body: JSON.stringify({ ids })
    });
    if (!res.ok) {
        const text = await res.text();
        alert(<?= jt('Export mislukt: ') ?> + text);
        return;
    }
    const blob = await res.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    const disposition = res.headers.get('Content-Disposition');
    let filename = 'onkostennotas.zip';
    if (disposition) {
        const match = /filename="?([^";]+)"?/i.exec(disposition);
        if (match) filename = match[1];
    }
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
}

async function handleBulkDelete() {
    const ids = gatherSelection();
    if (!ids.length) return;
    if (!confirm(<?= jt('Weet je zeker dat je deze onkostennota\'s wil verwijderen? Dit kan niet ongedaan gemaakt worden.') ?>)) {
        return;
    }
    const res = await fetch('bulk-delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': <?= json_encode(csrf_token()) ?> },
        body: JSON.stringify({ ids })
    });
    if (!res.ok) {
        const text = await res.text();
        alert(<?= jt('Verwijderen mislukt: ') ?> + text);
        return;
    }
    const { removed } = await res.json();
    rowCheckboxes.forEach(cb => {
        if (removed.includes(parseInt(cb.value, 10))) {
            cb.checked = false;
            const row = cb.closest('tr');
            if (row) row.remove();
        }
    });
    window.location.reload();
}

if (bulkExport) {
    bulkExport.addEventListener('click', handleBulkExport);
}
if (bulkDelete) {
    bulkDelete.addEventListener('click', handleBulkDelete);
}

async function updateStatus(id, status) {
    const res = await fetch('update-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': <?= json_encode(csrf_token()) ?> },
        body: JSON.stringify({ id, status })
    });
    if (!res.ok) {
        const text = await res.text();
        alert(<?= jt('Status bijwerken mislukt: ') ?> + text);
    }
    window.location.reload();
}

document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', (event) => {
        const id = event.target.getAttribute('data-id');
        updateStatus(id, event.target.value);
    });
});

updateSelectionState();
</script>
<?php
renderPageEnd();

function sortUrl(string $column, string $currentSort, string $currentOrder, string $category, string $recipient, string $status): string
{
    $params = [
        'sort' => $column,
        'order' => ($currentSort === $column && $currentOrder === 'ASC') ? 'DESC' : 'ASC'
    ];
    if ($category !== '') $params['category_id'] = $category;
    if ($recipient !== '') $params['recipient'] = $recipient;
    if ($status !== '') $params['status'] = $status;
    foreach (['q','month'] as $filter) if (!empty($_GET[$filter])) $params[$filter]=$_GET[$filter];
    if (in_array($_GET['expense_type'] ?? '', ['business', 'reimbursement'], true)) $params['expense_type'] = $_GET['expense_type'];
    return 'index.php?' . http_build_query($params);
}
