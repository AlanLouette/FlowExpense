<?php
require_once __DIR__ . '/bootstrap.php';
requireLogin();
enforceOrganizationAccess($currentOrganization ?? null);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(t('Method Not Allowed'));
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
    http_response_code(400);
    exit(t('Invalid payload'));
}

$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = $data['status'] ?? '';

if ($id <= 0) {
    http_response_code(400);
    exit(t('Ongeldig ID.'));
}

$allowed = ['Open', 'Paid', 'Complete'];
if (!in_array($status, $allowed, true)) {
    http_response_code(400);
    exit(t('Ongeldige status.'));
}

$stmt = $db->prepare('SELECT organization_id, user_id, status FROM expense_reports WHERE id = ? AND deleted_at IS NULL');
$stmt->execute([$id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report || (int)$report['organization_id'] !== (int)$currentOrganization['id']) {
    http_response_code(404);
    exit(t('Onkostennota niet gevonden.'));
}

$isAdmin = $currentUser && (int)$currentUser['is_admin'] === 1;
if (!$isAdmin && (int)$report['user_id'] !== (int)$currentUser['id']) {
    http_response_code(403);
    exit(t('Geen toestemming om de status te wijzigen.'));
}

$db->beginTransaction();
$stmt = $db->prepare('UPDATE expense_reports SET status = ? WHERE id = ?');
$stmt->execute([$status, $id]);
record_event('status_changed',$id,['before'=>$report['status'],'after'=>$status]);
$db->commit();

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
