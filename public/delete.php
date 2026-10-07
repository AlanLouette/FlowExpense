<?php
require_once __DIR__ . '/bootstrap.php';
requireLogin(); require_post(); enforceOrganizationAccess($currentOrganization);
$id=(int)($_POST['id'] ?? 0); $report=accessible_report($id);
$db->beginTransaction();
$db->prepare('UPDATE expense_reports SET deleted_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);
record_event('expense_deleted',$id,['reference'=>$report['custom_id']]);
$db->commit();
header('Location: '.safe_redirect($_POST['redirect'] ?? 'index.php'));
