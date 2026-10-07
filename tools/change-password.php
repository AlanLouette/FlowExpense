<?php
if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}
if ($argc!==2) {fwrite(STDERR,"Usage: php tools/change-password.php email\nPassword is read from standard input, never from the command line.\n");exit(1);}
require_once __DIR__.'/../public/bootstrap.php';
$password=rtrim(stream_get_contents(STDIN),"\r\n");
if (strlen($password)<12) {fwrite(STDERR,"Use at least 12 characters.\n");exit(1);}
$stmt=$db->prepare('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE email=? AND active=1');$stmt->execute([password_hash($password,PASSWORD_DEFAULT),$argv[1]]);
if (!$stmt->rowCount()) {fwrite(STDERR,"Active account not found.\n");exit(1);}
echo "Password updated. Existing sessions invalidated.\n";
