<?php
// Public demo workspaces never use EXPENSE_DB_PATH from the private installation.
function guest_available(): bool { return getenv('FLOWEXPENSE_DEMO') === '1' || getenv('GUEST_ENABLED') === '1'; }
function demo_mode(): bool { return getenv('FLOWEXPENSE_DEMO') === '1' || (getenv('GUEST_ENABLED') === '1' && !empty($_SESSION['demo_workspace'])); }
function demo_root(): string {
    $configured = getenv('DEMO_STORAGE_ROOT');
    if (!$configured || $configured[0] !== '/' || !is_dir($configured)) throw new RuntimeException('Create a dedicated absolute DEMO_STORAGE_ROOT outside public_html.');
    $root = realpath($configured);
    $web = realpath(__DIR__ . '/..');
    if (!$root || !$web || $root === $web || str_starts_with($root, $web . '/') || preg_match('~/public_html(?:/|$)~', $root)) throw new RuntimeException('Demo storage must be outside the public web directory.');
    return $root;
}
function demo_remove_directory(string $path): void {
    if (is_link($path)) { unlink($path); return; }
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $file) {
        if ($file->isDir() && !$file->isLink()) demo_remove_directory($file->getPathname()); else unlink($file->getPathname());
    }
    rmdir($path);
}
function demo_cleanup(string $root): int {
    $removed = 0;
    foreach (glob($root . '/guest-*', GLOB_ONLYDIR) ?: [] as $path) {
        if (is_link($path) || !preg_match('/^guest-[a-f0-9]{64}$/', basename($path))) continue;
        $born = (int)@file_get_contents($path . '/created-at');
        if (!$born || time() - $born < 86400) continue;
        $lock = fopen($path . '/application.lock', 'c');
        if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
            demo_remove_directory($path); $removed++; flock($lock, LOCK_UN);
        }
        if ($lock) fclose($lock);
    }
    return $removed;
}
function demo_landing(): never {
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html><html lang="<?= app_language() ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title><?= ht('Sign in to FlowExpense') ?></title><style>body{font-family:system-ui;background:#f4f6fc;color:#1f2937;display:grid;place-items:center;min-height:100vh;margin:0}.welcome{max-width:460px;background:white;border-radius:20px;padding:36px;margin:20px;box-shadow:0 15px 50px #152b4d10}h1{font-size:28px;margin:12px 0}p{color:#64748b;line-height:1.6}button{width:100%;border:0;border-radius:10px;padding:14px;background:linear-gradient(135deg,#3b82f6,#1f2937);color:white;font:inherit;font-weight:600;cursor:pointer}select{padding:10px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:20px;width:100%;font:inherit}a{color:#2563eb}.brand{font-weight:700;font-size:20px}label{display:block;margin-bottom:8px;font-size:14px}</style></head><body><main class="welcome"><div class="brand">● FlowExpense</div><h1><?= ht('Sign in to FlowExpense') ?></h1><p><?= ht('You are visiting the public guest version of FlowExpense. Personal accounts are accessed through the private application.') ?></p><h2 style="font-size:18px;margin-top:24px"><?= ht('Guest access') ?></h2><p><?= ht('Explore expenses, receipts and exports in your own temporary guest workspace. No account or password needed.') ?></p><p><?= ht('Fictional data only. Do not upload personal or confidential documents. This workspace expires after 24 hours.') ?></p><form method="post" action="guest.php"><?php csrf_field(); ?><label for="guest-language"><?= ht('Language') ?></label><select id="guest-language" name="language"><option value="fr" <?= app_language()==='fr'?'selected':'' ?>>Français</option><option value="en" <?= app_language()==='en'?'selected':'' ?>>English</option><option value="nl" <?= app_language()==='nl'?'selected':'' ?>>Nederlands</option></select><button type="submit"><?= ht('Try as a guest') ?></button></form><p><a href="https://alanlouette.be/portfolio/"><?= ht('Back to portfolio') ?></a></p></main></body></html><?php exit;
}
function demo_prepare(): void {
    if (!guest_available()) return;
    $legacyGuestOnly = getenv('FLOWEXPENSE_DEMO') === '1';
    $entryPost = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'guest.php' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    if (!$legacyGuestOnly && empty($_SESSION['demo_workspace']) && !$entryPost) return;
    if (PHP_SAPI === 'cli') throw new RuntimeException('Use tools/demo-cleanup.php for demo maintenance.');
    require_once __DIR__ . '/i18n.php';
    require_once __DIR__ . '/security.php';
    verify_csrf();
    header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; object-src 'none'; form-action 'self'");
    foreach (['pdo_sqlite','zip','mbstring','dom','xml','xmlreader','xmlwriter','gd','fileinfo'] as $extension) { if (!extension_loaded($extension)) { http_response_code(503);exit('PHP extension required: '.htmlspecialchars($extension)); } }
    try { $root = demo_root(); } catch (Throwable $e) { http_response_code(503); exit('Demo storage is not configured.'); }
    $rootLock = fopen($root . '/demo.lock', 'c');
    if (!$rootLock || !flock($rootLock, LOCK_EX)) throw new RuntimeException('Demo unavailable');
    demo_cleanup($root);
    $token = $_SESSION['demo_workspace'] ?? '';
    $path = is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token) ? $root . '/guest-' . $token : '';
    if (!$path || !is_dir($path) || time() - (int)@file_get_contents($path . '/created-at') >= 86400) {
        unset($_SESSION['demo_workspace'], $_SESSION['user_id'], $_SESSION['organization_id'], $_SESSION['session_version']);
        $path = '';
    }
    $oldPath = $path;
    $entry = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'guest.php';
    if ($entry && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (!$path || ($_POST['action'] ?? '') === 'reset')) {
        if (count(glob($root . '/guest-*', GLOB_ONLYDIR) ?: []) >= 100) { flock($rootLock,LOCK_UN);fclose($rootLock);http_response_code(429);exit(t('The demo is busy. Please try again later.')); }
        $identifier = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $rateFile = $root . '/creation-rates.json';
        $rates = json_decode(@file_get_contents($rateFile) ?: '{}', true) ?: [];
        foreach ($rates as $ip => $times) { $rates[$ip] = array_values(array_filter($times,fn($at)=>$at>time()-3600)); if (!$rates[$ip]) unset($rates[$ip]); }
        if (count($rates[$identifier] ?? []) >= 10) { flock($rootLock,LOCK_UN);fclose($rootLock);http_response_code(429);exit(t('The demo is busy. Please try again later.')); }
        $rates[$identifier][] = time();file_put_contents($rateFile,json_encode($rates),LOCK_EX);
        session_regenerate_id(true);
        $_SESSION['demo_workspace'] = bin2hex(random_bytes(32));
        unset($_SESSION['user_id'], $_SESSION['organization_id'], $_SESSION['session_version']);
        $path = $root . '/guest-' . $_SESSION['demo_workspace'];
        mkdir($path,0700);file_put_contents($path . '/created-at',(string)time());
        if ($oldPath) {
            $oldLock=fopen($oldPath.'/application.lock','c');
            if ($oldLock && flock($oldLock,LOCK_EX|LOCK_NB)) { demo_remove_directory($oldPath);flock($oldLock,LOCK_UN); }
            if ($oldLock) fclose($oldLock);
        }
        if (in_array($_POST['language'] ?? '', ['fr','en','nl'],true)) $_SESSION['language'] = $_POST['language'];
    }
    if (!$path) {
        flock($rootLock, LOCK_UN);fclose($rootLock);
        if ($legacyGuestOnly) demo_landing();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { http_response_code(403);exit(t('Your session has changed. Reload the page and try again.')); }
        header('Location: login.php');exit;
    }
    // Acquire the workspace lock before releasing the cleanup lock to avoid a deletion race.
    $GLOBALS['demoStorageLock'] = fopen($path . '/application.lock', 'c');
    if (!$GLOBALS['demoStorageLock'] || !flock($GLOBALS['demoStorageLock'],LOCK_EX)) throw new RuntimeException('Guest storage unavailable');
    flock($rootLock,LOCK_UN);fclose($rootLock);
    putenv('EXPENSE_DB_PATH=' . $path . '/expenses.db');putenv('BACKUP_AUTOMATIC=0');
}
function demo_seed(PDO $db, int $orgId): void {
    $db->exec('CREATE TABLE IF NOT EXISTS demo_marker (id INTEGER PRIMARY KEY CHECK(id=1))');
    $marked = $db->query('SELECT COUNT(*) FROM demo_marker')->fetchColumn();
    if (!$marked && $db->query('SELECT COUNT(*) FROM users')->fetchColumn()) throw new RuntimeException('Refusing to use an existing non-demo database.');
    if (!$marked) {
        require_once __DIR__ . '/expenses.php';
        $db->beginTransaction();
        $db->exec('INSERT INTO demo_marker(id) VALUES(1)');
        $db->prepare("UPDATE organizations SET name=?,legal_name=?,usage_mode='solo',number_prefix='DEMO',next_expense_number=6 WHERE id=?")->execute([t('Guest workspace'),t('Fictional demonstration organization'),$orgId]);
        $db->prepare('INSERT INTO users(name,email,password_hash,is_admin,active) VALUES(?,?,?,0,1)')->execute(['Guest','guest@example.invalid',password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);
        $userId=(int)$db->lastInsertId();
        $db->prepare("INSERT INTO user_organizations(user_id,organization_id,role) VALUES(?,?,'member')")->execute([$userId,$orgId]);
        $cats=[];foreach(['Déplacements','Fournitures','Hébergement','Repas'] as $name){$db->prepare('INSERT INTO expense_categories(organization_id,name) VALUES(?,?)')->execute([$orgId,$name]);$cats[$name]=(int)$db->lastInsertId();}
        foreach(['pièce','jour','km'] as $unit)$db->prepare('INSERT OR IGNORE INTO units(organization_id,name) VALUES(?,?)')->execute([$orgId,$unit]);
        $db->prepare('INSERT INTO recipients(organization_id,name,address,iban,bank_name) VALUES(?,?,?,?,?)')->execute([$orgId,'Camille Exemple','Adresse fictive — démonstration','BE00 0000 0000 0000','Banque fictive']);
        $examples=[['Studio Pixel','Abonnement logiciel','Fournitures',45,21,'Paid'],['Café du Parc','Déjeuner de réunion','Repas',32,12,'Complete'],['Rail Express','Déplacement professionnel','Déplacements',24,6,'Open'],['Cloud Atelier','Hébergement du site','Hébergement',59,21,'Paid'],['Bureau & Co','Accessoires de bureau','Fournitures',78.5,21,'Open']];
        mkdir(dirname(getenv('EXPENSE_DB_PATH')) . '/attachments',0700);
        foreach($examples as $i=>[$supplier,$description,$cat,$net,$vat,$status]){
            $totals=expense_totals([['quantity'=>1,'rate'=>$net,'vat_rate'=>$vat]]);
            $db->prepare('INSERT INTO expense_reports(organization_id,user_id,custom_id,recipient,description,date,total,status,category_id,expense_type,supplier,net_total,vat_total) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$orgId,$userId,'DEMO-'.str_pad((string)($i+1),4,'0',STR_PAD_LEFT),'','EXEMPLE — '.$description,date('Y-m-').str_pad((string)($i+1),2,'0',STR_PAD_LEFT),$totals['gross'],$status,$cats[$cat],'business',$supplier,$totals['net'],$totals['vat']]);
            $reportId=(int)$db->lastInsertId();
            $db->prepare('INSERT INTO expense_lines(report_id,description,quantity,unit,rate,vat_rate) VALUES(?,?,1,?,?,?)')->execute([$reportId,$description,'pièce',$net,$vat]);
            // Genuine downloadable PDF receipt, generated from fictional content only.
            require_once __DIR__ . '/../vendor/autoload.php';
            $pdf=new Dompdf\Dompdf();$pdf->loadHtml('<h1>FACTURE FICTIVE — DEMONSTRATION</h1><p>'.htmlspecialchars($supplier).'</p><p>'.htmlspecialchars($description).'</p><p>HT: '.$net.' EUR · TVA: '.$vat.'% · TTC: '.$totals['gross'].' EUR</p><p>Ce document est un exemple sans valeur comptable.</p>');$pdf->render();$bytes=$pdf->output();
            $stored='demo-'.$reportId.'.pdf';file_put_contents(dirname(getenv('EXPENSE_DB_PATH')).'/attachments/'.$stored,$bytes);
            $db->prepare('INSERT INTO expense_attachments(report_id,original_name,stored_name,mime,size_bytes) VALUES(?,?,?,?,?)')->execute([$reportId,'facture-fictive-'.($i+1).'.pdf',$stored,'application/pdf',strlen($bytes)]);
        }
        $db->commit();
    }
    $user=$db->query('SELECT * FROM users WHERE is_admin=0 AND active=1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new RuntimeException('Guest account unavailable');
    $_SESSION['user_id']=(int)$user['id'];$_SESSION['organization_id']=$orgId;$_SESSION['session_version']=(int)$user['session_version'];
}
function demo_check_limits(PDO $db): void {
    if (!demo_mode() || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $page=basename($_SERVER['SCRIPT_NAME'] ?? '');
    $deny = (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8*1024*1024;
    foreach ($_POST as $value) {
        foreach (is_array($value) ? $value : [$value] as $item) if (!is_scalar($item) || strlen((string)$item)>4000) $deny=true;
    }
    $operationsFile=dirname(getenv('EXPENSE_DB_PATH')).'/operations';
    $operations=(int)@file_get_contents($operationsFile);
    if ($operations>=600) $deny=true;
    else file_put_contents($operationsFile,(string)($operations+1));
    if (($page==='save.php' && empty($_POST['id'])) || $page==='duplicate.php') $deny=$deny || $db->query('SELECT COUNT(*) FROM expense_reports')->fetchColumn()>=100;
    if ($page==='save.php') $deny=$deny || count((array)($_POST['description_line'] ?? []))>50;
    if ($page==='upload-attachment.php') {
        $sizes=(array)($_FILES['files']['size'] ?? []);
        $deny=$deny || count($sizes)>5 || array_sum($sizes)>5*1024*1024 || max([0,...$sizes])>2*1024*1024 || (int)$db->query('SELECT COALESCE(SUM(size_bytes),0) FROM expense_attachments')->fetchColumn()+array_sum($sizes)>20*1024*1024;
    }
    foreach (['categories.php'=>'expense_categories','units.php'=>'units','recipients.php'=>'recipients'] as $endpoint=>$table) {
        if ($page===$endpoint && empty($_POST['id']) && !isset($_POST['delete_id'])) $deny=$deny || $db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()>=100;
    }
    if ($deny) { http_response_code(413);exit(t('Demo limit reached. Reset your workspace to start again.')); }
}
