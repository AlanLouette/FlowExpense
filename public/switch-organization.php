<?php
require_once __DIR__ . '/bootstrap.php';
requireLogin();
require_post();

$organizationId = isset($_POST['organization_id']) ? (int)$_POST['organization_id'] : 0;

$allowed = false;
foreach ($availableOrganizations as $org) {
    if ((int)$org['id'] === $organizationId) {
        $allowed = true;
        break;
    }
}

if ($allowed) {
    $_SESSION['organization_id'] = $organizationId;
}

$redirect = safe_redirect($_POST['return'] ?? 'index.php');
header('Location: ' . $redirect);
exit;
