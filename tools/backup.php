<?php
if (PHP_SAPI !== 'cli') { http_response_code(404);exit; }
require_once __DIR__.'/../public/bootstrap.php';
if (!maybe_daily_backup(true)) { fwrite(STDERR,"Daily backup failed. Check storage and receipt files.\n");exit(1); }
echo "Daily backup available.\n";
