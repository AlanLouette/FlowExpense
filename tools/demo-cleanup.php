<?php
if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}
require_once __DIR__ . '/../public/lib/demo.php';
// Provide the dedicated storage directory as the sole argument. No private DB is opened.
if ($argc !== 2) {fwrite(STDERR,"Usage: php tools/demo-cleanup.php /absolute/private/guest-storage\n");exit(1);}
putenv('DEMO_STORAGE_ROOT='.$argv[1]);
$root=demo_root();$lock=fopen($root.'/demo.lock','c');
if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Storage unavailable');
echo demo_cleanup($root)," expired guest workspaces removed.\n";
flock($lock,LOCK_UN);fclose($lock);
