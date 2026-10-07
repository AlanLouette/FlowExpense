<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
requireLogin(); enforceOrganizationAccess($currentOrganization);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)($_POST['id'] ?? 0);
    $kind=$_POST['kind'] ?? 'expense';
    $db->beginTransaction();
    if ($kind==='attachment') {
        $stmt=$db->prepare('SELECT * FROM expense_attachments WHERE id=? AND deleted_at IS NOT NULL');$stmt->execute([$id]);$attachment=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$attachment) { $db->rollBack();http_response_code(404);exit(t('Bijlage niet gevonden.')); }
        accessible_report((int)$attachment['report_id']);
        $db->prepare('UPDATE expense_attachments SET deleted_at=NULL WHERE id=?')->execute([$id]);
        record_event('attachment_restored',(int)$attachment['report_id'],['name'=>$attachment['original_name']]);
    } elseif ($kind==='expense') {
        $report=accessible_report($id,true);
        $db->prepare('UPDATE expense_reports SET deleted_at=NULL WHERE id=?')->execute([$id]);
        record_event('expense_restored',$id,['reference'=>$report['custom_id']]);
    } else { $db->rollBack();http_response_code(400);exit(t('Invalid payload')); }
    $db->commit();header('Location: trash.php');exit;
}
$where='organization_id=? AND deleted_at IS NOT NULL';$params=[$currentOrganization['id']];
if (!(int)$currentUser['is_admin']) {$where.=' AND user_id=?';$params[]=$currentUser['id'];}
$stmt=$db->prepare('SELECT * FROM expense_reports WHERE '.$where.' ORDER BY deleted_at DESC');$stmt->execute($params);$reports=$stmt->fetchAll(PDO::FETCH_ASSOC);
$where='r.organization_id=? AND r.deleted_at IS NULL AND a.deleted_at IS NOT NULL';$params=[$currentOrganization['id']];
if (!(int)$currentUser['is_admin']) {$where.=' AND r.user_id=?';$params[]=$currentUser['id'];}
$stmt=$db->prepare('SELECT a.*,r.custom_id FROM expense_attachments a JOIN expense_reports r ON r.id=a.report_id WHERE '.$where.' ORDER BY a.deleted_at DESC');$stmt->execute($params);$attachments=$stmt->fetchAll(PDO::FETCH_ASSOC);
renderPageStart(t('Trash'),'trash');
?>
<section class="card">
    <p class="notice"><?= ht('Deleted expenses and receipts are kept here until you restore them.') ?></p>
    <?php if (!$reports && !$attachments): ?><p><?= ht('The trash is empty.') ?></p><?php endif; ?>
    <?php if ($reports): ?><h2><?= ht('Onkostennota\'s') ?></h2><table><thead><tr><th><?= ht('Reference') ?></th><th><?= ht('Description') ?></th><th><?= ht('Amount including VAT') ?></th><th><?= ht('Deleted on') ?></th><th><?= ht('Acties') ?></th></tr></thead><tbody>
        <?php foreach ($reports as $report): ?><tr><td><?= htmlspecialchars($report['custom_id']) ?></td><td><?= htmlspecialchars($report['description']) ?></td><td>€ <?= localized_number((float)$report['total'],2) ?></td><td><?= htmlspecialchars($report['deleted_at']) ?></td><td class="table-actions"><form method="post" class="inline-action"><?php csrf_field(); ?><input type="hidden" name="id" value="<?= (int)$report['id'] ?>"><button type="submit"><?= ht('Restore') ?></button></form></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?>
    <?php if ($attachments): ?><h2><?= ht('Attachments') ?></h2><table><thead><tr><th><?= ht('Reference') ?></th><th><?= ht('Naam') ?></th><th><?= ht('Acties') ?></th></tr></thead><tbody>
        <?php foreach ($attachments as $attachment): ?><tr><td><?= htmlspecialchars($attachment['custom_id']) ?></td><td><?= htmlspecialchars($attachment['original_name']) ?></td><td class="table-actions"><form method="post" class="inline-action"><?php csrf_field(); ?><input type="hidden" name="kind" value="attachment"><input type="hidden" name="id" value="<?= (int)$attachment['id'] ?>"><button type="submit"><?= ht('Restore') ?></button></form></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?>
</section>
<?php renderPageEnd(); ?>
