<?php
require_once __DIR__ . '/bootstrap.php';
$language = $_POST['language'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !in_array($language, ['fr', 'en', 'nl'], true)) {
    http_response_code(400);
    exit('Invalid language');
}
$_SESSION['language'] = $language;
setcookie('flowexpense_language', $language, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => getenv('APP_HTTPS') === '1' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')]);
$return = $_POST['return'] ?? 'index.php';
$return = safe_redirect($return);
header('Location: ' . $return);
