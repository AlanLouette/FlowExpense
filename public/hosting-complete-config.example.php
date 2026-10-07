<?php
// Same URL for real accounts and guests. No credentials belong in this public file.
if (basename(dirname(__DIR__)) !== 'public_html') { http_response_code(503);exit('Install in public_html/flowexpense.'); }
$storageBase = dirname(__DIR__,2);
$privateStorage = $storageBase . '/flowexpense-private';
$guestStorage = $storageBase . '/flowexpense-guest-data';
foreach ([$privateStorage,$guestStorage] as $directory) {
    if (!is_dir($directory) && !mkdir($directory,0700,true)) { http_response_code(503);exit('Cannot create private application storage.'); }
}
putenv('FLOWEXPENSE_DEMO=0');
putenv('GUEST_ENABLED=1');
putenv('DEMO_STORAGE_ROOT='.$guestStorage);
putenv('EXPENSE_DB_PATH='.$privateStorage.'/expenses.db');
putenv('BACKUP_DIR='.$privateStorage.'/backups');
putenv('FLOWEXPENSE_ENV_FILE='.$privateStorage.'/.env');
putenv('APP_ENV=production');
putenv('APP_HTTPS=1');
putenv('APP_TIMEZONE=Europe/Brussels');
