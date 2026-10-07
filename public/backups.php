<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';
requireAdmin(); enforceOrganizationAccess($currentOrganization);
$error=null;
if (isset($_GET['download'])) {
    try {
        $path=backup_path($_GET['download']);
        header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.basename($path).'"');header('Content-Length: '.filesize($path));readfile($path);exit;
    } catch (Throwable $exception) {http_response_code(404);exit(t('Invalid backup.'));}
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (($_POST['action'] ?? '')==='restore') {
            if (!password_verify($_POST['password'] ?? '',$currentUser['password_hash'])) throw new RuntimeException(t('Ongeldige gebruikersnaam of wachtwoord.'));
            if (($_POST['confirmation'] ?? '')!=='RESTORE') throw new RuntimeException(t('Type RESTORE to confirm.'));
            $name=$_POST['backup'] ?? '';restore_backup($name);
            $db=new PDO('sqlite:'.$dbPath);$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
            // Invalidate every restored login. Restored accounts must sign in again.
            $db->exec('UPDATE users SET session_version=session_version+1');
            record_event('backup_restored',null,['backup'=>$name]);
            $_SESSION=[];session_regenerate_id(true);
            header('Location: login.php');exit;
        }
        if (($_POST['action'] ?? '')!=='create') throw new RuntimeException(t('Invalid payload'));
        $name=create_backup();record_event('backup_created',null,['backup'=>$name]);
        unset($_SESSION['backup_error']);header('Location: backups.php');exit;
    } catch (Throwable $exception) {$error=$exception->getMessage();}
}
$backups=backup_list();
renderPageStart(t('Backups'),'backups');
?>
<section class="card">
    <p class="notice"><?= ht('Backups contain all organizations, accounts and receipts. Keep downloaded archives private.') ?></p>
    <p><?= ht('A daily backup is created on the first authenticated visit. The latest 14 daily archives are retained. Manual backups are kept.') ?></p>
    <?php if ($error): ?><p class="notice"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post" class="inline-action"><?php csrf_field(); ?><input type="hidden" name="action" value="create"><button type="submit"><?= ht('Create backup now') ?></button></form>
    <?php if ($backups): ?><table style="margin-top:24px"><thead><tr><th><?= ht('Naam') ?></th><th><?= ht('Date') ?></th><th><?= ht('Size') ?></th><th><?= ht('Acties') ?></th></tr></thead><tbody>
        <?php foreach ($backups as $backup): ?><tr><td><?= htmlspecialchars($backup['name']) ?></td><td><?= htmlspecialchars(date('Y-m-d H:i',$backup['modified'])) ?></td><td><?= localized_number($backup['size']/1024,1) ?> KB</td><td><a href="backups.php?download=<?= urlencode($backup['name']) ?>"><?= ht('Download') ?></a></td></tr><?php endforeach; ?>
    </tbody></table><?php else: ?><p><?= ht('No backups yet.') ?></p><?php endif; ?>
</section>
<?php if ($backups): ?>
<section class="card">
    <h2><?= ht('Restore a backup') ?></h2>
    <p class="notice"><?= ht('Restoration replaces all current data. A safety backup is made first. All users will be signed out.') ?></p>
    <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="restore">
        <label><?= ht('Backup') ?><select name="backup"><?php foreach ($backups as $backup): ?><option value="<?= htmlspecialchars($backup['name']) ?>"><?= htmlspecialchars($backup['name']) ?></option><?php endforeach; ?></select></label>
        <label><?= ht('Current password') ?><input type="password" name="password" autocomplete="current-password" required></label>
        <label><?= ht('Type RESTORE to confirm.') ?><input type="text" name="confirmation" pattern="RESTORE" autocomplete="off" required></label>
        <button type="submit"><?= ht('Restore a backup') ?></button>
    </form>
</section>
<?php endif; renderPageEnd(); ?>
