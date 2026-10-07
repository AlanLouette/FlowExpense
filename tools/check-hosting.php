<?php
if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}
$ok=version_compare(PHP_VERSION,'8.2.0','>=');
echo 'PHP 8.2+: ', $ok ? 'OK' : 'MISSING', PHP_EOL;
foreach (['pdo_sqlite','zip','mbstring','dom','xml','xmlreader','xmlwriter','gd','fileinfo'] as $extension) {
    $available=extension_loaded($extension);$ok=$ok && $available;
    echo $extension,': ',$available?'OK':'MISSING',PHP_EOL;
}
if (extension_loaded('pdo_sqlite')) {
    $db=new PDO('sqlite::memory:');$version=$db->query('SELECT sqlite_version()')->fetchColumn();
    $sqliteOk=version_compare($version,'3.27.0','>=');$ok=$ok && $sqliteOk;
    echo 'SQLite 3.27+ (VACUUM INTO): ',$sqliteOk?'OK':'MISSING',PHP_EOL;
}
exit($ok?0:1);
