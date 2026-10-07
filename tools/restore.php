<?php
if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}
if ($argc!==3 || $argv[2]!=='--confirm') {fwrite(STDERR,"Usage: php tools/restore.php backup-filename.zip --confirm\nRestores the entire application; creates a safety archive first.\n");exit(1);}
require_once __DIR__.'/../public/bootstrap.php';
try {restore_backup($argv[1]);$db=new PDO('sqlite:'.$dbPath);$db->exec('UPDATE users SET session_version=session_version+1');echo "Restored. All users must sign in again.\n";} catch (Throwable $error) {fwrite(STDERR,$error->getMessage().PHP_EOL);exit(1);}
