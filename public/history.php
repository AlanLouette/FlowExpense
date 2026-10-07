<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
requireLogin(); enforceOrganizationAccess($currentOrganization);
$where='(e.organization_id=?)';$params=[$currentOrganization['id']];
if (!(int)$currentUser['is_admin']) { $where.=' AND (r.user_id=? OR (e.report_id IS NULL AND e.user_id=?))';$params[]=$currentUser['id'];$params[]=$currentUser['id']; }
$cursor=(int)($_GET['before'] ?? 0);
if ($cursor>0) {$where.=' AND e.id<?';$params[]=$cursor;}
$stmt=$db->prepare('SELECT e.*,u.name AS actor,r.custom_id FROM audit_events e LEFT JOIN users u ON u.id=e.user_id LEFT JOIN expense_reports r ON r.id=e.report_id WHERE '.$where.' ORDER BY e.id DESC LIMIT 100');$stmt->execute($params);$events=$stmt->fetchAll(PDO::FETCH_ASSOC);
$actions=['expense_created'=>'Expense created','expense_updated'=>'Expense updated','expense_deleted'=>'Moved to trash','expense_restored'=>'Expense restored','expense_duplicated'=>'Expense duplicated','status_changed'=>'Status changed','attachment_uploaded'=>'Receipt added','attachment_deleted'=>'Receipt moved to trash','attachment_restored'=>'Receipt restored','category_deleted'=>'Category deleted','recipient_deleted'=>'Recipient deleted','settings_updated'=>'Settings updated','user_updated'=>'User updated','user_created'=>'User created','backup_created'=>'Backup created','backup_restored'=>'Backup restored'];
renderPageStart(t('History'),'history');
?>
<section class="card">
    <p class="notice"><?= ht('History starts when this feature is installed. Earlier changes are not reconstructed.') ?></p>
    <?php if (!$events): ?><p><?= ht('No changes recorded yet.') ?></p><?php else: ?>
    <table><thead><tr><th><?= ht('Date') ?></th><th><?= ht('Owner') ?></th><th><?= ht('Reference') ?></th><th><?= ht('Action') ?></th><th><?= ht('Details') ?></th></tr></thead><tbody>
    <?php foreach ($events as $event): ?><tr><td><?= htmlspecialchars($event['created_at']) ?></td><td><?= htmlspecialchars($event['actor'] ?? '-') ?></td><td><?= htmlspecialchars($event['custom_id'] ?? '-') ?></td><td><?= ht($actions[$event['action']] ?? $event['action']) ?></td><td><details><summary><?= ht('View') ?></summary><pre style="white-space:pre-wrap;max-width:400px"><?= htmlspecialchars(json_encode(json_decode($event['details'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre></details></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php if (count($events)===100): ?><a href="history.php?before=<?= (int)end($events)['id'] ?>"><?= ht('Older changes') ?></a><?php endif; ?>
    <?php endif; ?>
</section>
<?php renderPageEnd(); ?>
