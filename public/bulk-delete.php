<?php
require_once __DIR__ . '/bootstrap.php';
requireLogin(); require_post(); enforceOrganizationAccess($currentOrganization);
$payload=json_decode(file_get_contents('php://input'),true);
if (!is_array($payload) || !is_array($payload['ids'] ?? null) || !$payload['ids']) { http_response_code(400); exit(t('Ongeldige selectie.')); }
$ids=array_unique(array_filter(array_map('intval',$payload['ids']),fn($id)=>$id>0));
$removed=[];
$db->beginTransaction();
$stmt=$db->prepare('SELECT * FROM expense_reports WHERE id=? AND organization_id=? AND deleted_at IS NULL');
foreach ($ids as $id) {
    $stmt->execute([$id,$currentOrganization['id']]);$report=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$report || (!(int)$currentUser['is_admin'] && (int)$report['user_id'] !== (int)$currentUser['id'])) continue;
    $db->prepare('UPDATE expense_reports SET deleted_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);
    record_event('expense_deleted',$id,['reference'=>$report['custom_id']]);$removed[]=$id;
}
$db->commit();
header('Content-Type: application/json');echo json_encode(['removed'=>$removed]);
