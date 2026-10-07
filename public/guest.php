<?php
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: login.php');exit; }
require_once __DIR__ . '/bootstrap.php';
if (!demo_mode()) { http_response_code(404); exit; }
header('Location: index.php');
