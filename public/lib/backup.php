<?php
function backup_directory(): string
{
    global $dbPath;
    return getenv('BACKUP_DIR') ?: dirname($dbPath) . '/backups';
}
function create_backup(string $label = 'manual'): string
{
    global $db, $dbPath;
    $directory = backup_directory();
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException(t('Backup failed.'));
    $base = 'backup-' . date('Ymd-His') . '-' . preg_replace('/[^a-z-]/', '', $label) . '-' . bin2hex(random_bytes(3)) . '.zip';
    $path = $directory . '/' . $base;
    $snapshot = $directory . '/snapshot-' . bin2hex(random_bytes(8)) . '.db';
    $zip = new ZipArchive();
    $opened = false;
    try {
        $snapshotDb = new PDO('sqlite:' . $dbPath);
        $snapshotDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $snapshotDb->exec('PRAGMA busy_timeout = 5000');
        $snapshotDb->exec('VACUUM INTO ' . $snapshotDb->quote($snapshot));
        $snapshotDb = null;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new RuntimeException(t('Backup failed.'));
        $opened = true;
        $files = ['expenses.db' => hash_file('sha256', $snapshot)];
        $zip->addFile($snapshot, 'expenses.db');
        $rows = $db->query('SELECT DISTINCT stored_name FROM expense_attachments')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $name) {
            if ($name !== basename($name)) throw new RuntimeException(t('Backup failed.'));
            $file = dirname($dbPath) . '/attachments/' . $name;
            if (!is_file($file)) throw new RuntimeException(t('Receipt files missing. No archive was generated.'));
            $zip->addFile($file, 'attachments/' . $name);
            $files['attachments/' . $name] = hash_file('sha256', $file);
        }
        $zip->addFromString('manifest.json', json_encode(['format'=>'flowexpense-backup-v1','created_at'=>date(DATE_ATOM),'files'=>$files], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if (!$zip->close()) throw new RuntimeException(t('Backup failed.'));
        $opened = false;
        chmod($path, 0600);
        return $base;
    } catch (Throwable $error) {
        if ($opened) $zip->close();
        if (is_file($path)) unlink($path);
        throw $error;
    } finally { if (is_file($snapshot)) unlink($snapshot); }
}
function backup_list(): array
{
    $files = glob(backup_directory() . '/backup-*.zip') ?: [];
    rsort($files);
    return array_map(fn($path)=>['name'=>basename($path),'size'=>filesize($path),'modified'=>filemtime($path)],$files);
}
function maybe_daily_backup(bool $scheduled = false): bool
{
    global $currentUser;
    if (!$currentUser && !$scheduled) return true;
    $marker = backup_directory() . '/last-daily.json';
    $last = is_file($marker) ? json_decode(file_get_contents($marker),true) : null;
    if (($last['day'] ?? '') === date('Y-m-d') && is_file(backup_directory() . '/' . ($last['name'] ?? ''))) return true;
    try {
        $name = create_backup('daily');
        file_put_contents($marker, json_encode(['day'=>date('Y-m-d'),'name'=>$name]), LOCK_EX);
        // Prune only automatically generated archives; manual/pre-restore archives are preserved.
        $daily = array_values(array_filter(backup_list(), fn($file)=>str_contains($file['name'],'-daily-')));
        foreach (array_slice($daily, 14) as $file) unlink(backup_directory() . '/' . $file['name']);
        unset($_SESSION['backup_error']);
        return true;
    } catch (Throwable $error) {
        $_SESSION['backup_error'] = true;
        error_log('FlowExpense automatic backup failed: ' . $error->getMessage());
        return false;
    }
}
function backup_path(string $name): string
{
    if (!preg_match('/^backup-[0-9]{8}-[0-9]{6}-[a-z-]+-[a-f0-9]{6}\.zip$/D', $name)) throw new InvalidArgumentException(t('Invalid backup.'));
    $path = backup_directory() . '/' . $name;
    if (!is_file($path)) throw new InvalidArgumentException(t('Invalid backup.'));
    return $path;
}
function restore_backup(string $name): void
{
    global $db, $dbPath;
    $zip = new ZipArchive();
    if ($zip->open(backup_path($name)) !== true) throw new RuntimeException(t('Invalid backup.'));
    $stage = dirname($dbPath) . '/restore-' . bin2hex(random_bytes(8));
    mkdir($stage, 0700);
    $dbMoved = false; $attachmentsMoved = false; $activated = false;
    $oldDb = $dbPath . '.before-restore-' . bin2hex(random_bytes(6));
    $attachmentDir = dirname($dbPath) . '/attachments';
    $oldAttachments = $attachmentDir . '.before-restore-' . bin2hex(random_bytes(6));
    try {
        $raw = $zip->getFromName('manifest.json');
        $manifest = $raw === false ? null : json_decode($raw, true);
        if (($manifest['format'] ?? '') !== 'flowexpense-backup-v1' || !isset($manifest['files']['expenses.db']) || !is_array($manifest['files'])) throw new RuntimeException(t('Invalid backup.'));
        $size = 0;
        foreach ($manifest['files'] as $entry=>$hash) {
            if ($entry !== 'expenses.db' && !preg_match('/^attachments\/[A-Za-z0-9_.-]+$/D',$entry)) throw new RuntimeException(t('Invalid backup.'));
            $stat = $zip->statName($entry);
            if (!$stat || ($size += $stat['size']) > 1073741824) throw new RuntimeException(t('Invalid backup.'));
            $bytes=$zip->getFromName($entry);
            if ($bytes === false || !hash_equals($hash,hash('sha256',$bytes))) throw new RuntimeException(t('Invalid backup.'));
            if ($entry !== 'expenses.db' && !is_dir($stage.'/attachments')) mkdir($stage.'/attachments',0700);
            if (file_put_contents($stage.'/'.$entry,$bytes) === false) throw new RuntimeException(t('Backup failed.'));
        }
        $candidate = new PDO('sqlite:'.$stage.'/expenses.db');
        if ($candidate->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $candidate->query('PRAGMA foreign_key_check')->fetch() !== false) throw new RuntimeException(t('Invalid backup.'));
        foreach (['users','organizations','user_organizations','expense_reports','expense_lines','expense_attachments'] as $table) $candidate->query("SELECT * FROM $table LIMIT 1");
        if (!$candidate->query('SELECT COUNT(*) FROM users WHERE is_admin=1 AND active=1')->fetchColumn()) throw new RuntimeException(t('Invalid backup.'));
        foreach ($candidate->query('SELECT stored_name FROM expense_attachments')->fetchAll(PDO::FETCH_COLUMN) as $file) {
            if ($file !== basename($file) || !isset($manifest['files']['attachments/'.$file])) throw new RuntimeException(t('Invalid backup.'));
        }
        $candidate = null;
        create_backup('before-restore');
        // All web requests and CLI operations share the application lock during replacement.
        $db = null;
        if (!rename($dbPath,$oldDb)) throw new RuntimeException(t('Backup failed.'));
        $dbMoved = true;
        if (is_dir($attachmentDir)) {
            if (!rename($attachmentDir,$oldAttachments)) throw new RuntimeException(t('Backup failed.'));
            $attachmentsMoved = true;
        }
        if (!is_dir($stage.'/attachments')) mkdir($stage.'/attachments',0700);
        if (!rename($stage.'/expenses.db',$dbPath)) throw new RuntimeException(t('Backup failed.'));
        if (!rename($stage.'/attachments',$attachmentDir)) throw new RuntimeException(t('Backup failed.'));
        $activated = true;
        // The pre-restore ZIP contains both original database and receipts; remove only temporary copies.
        if (is_file($oldDb)) unlink($oldDb);
        remove_backup_stage($oldAttachments);
    } catch (Throwable $error) {
        if (!$activated && $dbMoved) {
            if (is_file($dbPath)) unlink($dbPath);
            rename($oldDb,$dbPath);
            if ($attachmentsMoved) { remove_backup_stage($attachmentDir); rename($oldAttachments,$attachmentDir); }
        }
        throw $error;
    } finally { $zip->close(); remove_backup_stage($stage); }
}
function remove_backup_stage(string $directory): void
{
    if (!is_dir($directory)) return;
    foreach (new DirectoryIterator($directory) as $item) {
        if ($item->isDot()) continue;
        if ($item->isDir() && !$item->isLink()) remove_backup_stage($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($directory);
}
