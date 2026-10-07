<?php
// The guest release builder installs this file as hosting-config.php.
// This automatic path targets public_html/flowexpense on Hostinger.
if (basename(dirname(__DIR__)) !== 'public_html') {
    http_response_code(503);exit('Install this guest release in public_html/flowexpense.');
}
$guestStorage = dirname(__DIR__, 2) . '/flowexpense-guest-data';
if (!is_dir($guestStorage) && !mkdir($guestStorage,0700,true)) {
    http_response_code(503);exit('Cannot create private guest storage.');
}
putenv('FLOWEXPENSE_DEMO=1');
putenv('DEMO_STORAGE_ROOT='.$guestStorage);
putenv('FLOWEXPENSE_ENV_FILE='.$guestStorage.'/.unused-env');
putenv('APP_ENV=production');
putenv('APP_HTTPS=1');
putenv('APP_TIMEZONE=Europe/Brussels');
putenv('BACKUP_AUTOMATIC=0');
