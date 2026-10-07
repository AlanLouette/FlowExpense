#!/usr/bin/env python3
"""HTTP regression checks using a disposable SQLite database and attachment directory.
Run: python3 tests/integration.py (PHP 8.2+ with project extensions required).
"""
import base64, csv, http.cookiejar, io, json, os, re, shutil, socket, sqlite3
import subprocess, tempfile, time, urllib.error, urllib.parse, urllib.request, zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'
checks = 0

def check(condition, message):
    global checks
    assert condition, message
    checks += 1

class Client:
    def __init__(self, base):
        self.base = base
        self.token = None
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
    def request(self, path, data=None, headers=None, csrf=True):
        headers = dict(headers or {})
        if data is not None and csrf:
            if self.token is None: self.request('login.php')
            headers['X-CSRF-Token'] = self.token
        if isinstance(data, dict):
            data = urllib.parse.urlencode(data, doseq=True).encode()
        request = urllib.request.Request(self.base + '/' + path, data=data, headers=headers or {})
        try:
            response = self.opener.open(request, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        body=response.read()
        tokens=re.findall(rb'name="csrf_token" value="([a-f0-9]+)"',body)
        if tokens:self.token=tokens[0].decode()
        return response.status, body, response.geturl()
    def login(self, email, password):
        status, body, _ = self.request('login.php', {'email': email, 'password': password})
        check(status == 200 and b'logout.php' in body, 'Login failed')
    def language(self, code):
        status, body, _ = self.request('language.php', {'language':code,'return':'index.php'})
        check(status == 200 and f'<html lang="{code}">'.encode() in body, 'Language did not change')

with tempfile.TemporaryDirectory(prefix='flowexpense-test-') as work:
    work = Path(work)
    database = work / 'expenses.db'
    # Start with a legacy schema to check the additive migration.
    schema = subprocess.check_output(['git','show','HEAD:public/db.php'],cwd=ROOT)
    legacy = work / 'legacy.php'
    legacy.write_bytes(schema)
    env = dict(os.environ, EXPENSE_DB_PATH=str(database), ADMIN_EMAIL='test-admin@example.com', ADMIN_PASSWORD='integration-only-password')
    subprocess.run(['php', str(legacy)], env=env, check=True)
    con = sqlite3.connect(database)
    con.execute("INSERT INTO organizations (id,name) VALUES (1,'Test organization'),(2,'Other organization')")
    password_hash = subprocess.check_output(['php','-r','echo password_hash("integration-only-password", PASSWORD_DEFAULT);']).decode()
    con.execute('INSERT INTO users (id,name,email,password_hash,is_admin) VALUES (10,?,?,?,0)', ('Test member','member@example.com',password_hash))
    con.execute('INSERT INTO users (id,name,email,password_hash,is_admin) VALUES (11,?,?,?,0)', ('Other member','other@example.com',password_hash))
    con.execute("INSERT INTO user_organizations VALUES (10,1,'member'),(11,2,'member')")
    con.execute("INSERT INTO expense_reports (id,organization_id,user_id,custom_id,recipient,date,total,description) VALUES (100,1,10,'LEGACY-100','Test member','2026-09-01',19.95,'Legacy description')")
    con.execute("INSERT INTO expense_lines (report_id,description,quantity,unit,rate) VALUES (100,'Legacy line',1,'item',19.95)")
    con.commit()
    sock = socket.socket(); sock.bind(('127.0.0.1',0)); port = sock.getsockname()[1]; sock.close()
    log = open(work/'server.log','w+')
    process = subprocess.Popen(['php','-d','display_errors=0','-d','error_reporting=32767','-d','session.save_path='+str(work),'-S',f'127.0.0.1:{port}','-t',str(PUBLIC)],env=env,stdout=log,stderr=log)
    try:
        base=f'http://127.0.0.1:{port}'
        admin = Client(base); member = Client(base); other = Client(base)
        for attempt in range(50):
            try:
                _,login_body,_=admin.request('login.php');
                check(b'https://alanlouette.be/flowexpense/' in login_body,'Public guest option missing from login');
                break
            except urllib.error.URLError: time.sleep(.1)
        else: raise RuntimeError('PHP test server did not start')
        check(con.execute('SELECT total,net_total,vat_total,expense_type FROM expense_reports WHERE id=100').fetchone() == (19.95,19.95,0,'reimbursement'), 'Legacy amount changed')
        admin.login('test-admin@example.com','integration-only-password')
        member.login('member@example.com','integration-only-password')
        other.login('other@example.com','integration-only-password')
        check(member.request('settings.php')[0]==403,'Member got administrator access')
        pages=['index.php','form.php','settings.php','users.php','organizations.php','categories.php','units.php','recipients.php','trash.php','history.php','backups.php']
        for language, label in [('fr','Paramètres'),('en','Settings'),('nl','Instellingen')]:
            admin.language(language)
            for page in pages:
                status, body, _ = admin.request(page)
                check(status==200 and f'<html lang="{language}">'.encode() in body and label.encode() in body, f'{page} translation/render failed ({language})')
                for script in re.findall(rb'<script[^>]*>(.*?)</script>',body,re.S):
                    js=work/'check.js';js.write_bytes(script)
                    node=shutil.which('node')
                    if node: subprocess.run([node,'--check',str(js)],check=True,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
        admin.language('fr')
        member.language('fr')
        con.execute("INSERT INTO expense_categories (id,organization_id,name) VALUES (50,1,'Test category'),(51,2,'Foreign category')")
        con.commit()
        settings={'usage_mode':'solo','legal_name':'Test organization','next_expense_number':'1','number_prefix':'EXP','branding_primary':'#1f2933','branding_accent':'#3b82f6'}
        check(admin.request('settings.php',settings)[0]==200,'Settings update failed')
        check(b'value="business" selected' in member.request('form.php')[1],'Solo mode did not default to business expense')
        data={'expense_type':'business','supplier':'Supplier <safe>','date':'2026-10-06','category_id':'50','description':'=SUM(1,1)','description_line[]':['Services','Train'],'quantity[]':['2','1'],'unit[]':['hour','trip'],'rate[]':['50','10'],'vat_rate[]':['21','6']}
        status, body, url = member.request('save.php',data)
        check(status==200 and 'form.php?id=' in url,'Business expense creation failed: '+body.decode()[:200])
        rid=int(url.split('id=')[1])
        check(con.execute('SELECT net_total,vat_total,total,supplier,user_id FROM expense_reports WHERE id=?',(rid,)).fetchone()==(110,21.6,131.6,'Supplier <safe>',10),'Incorrect tax totals or owner')
        check(b'Supplier &lt;safe&gt;' in body,'Supplier was not escaped')
        before=con.execute('SELECT COUNT(*) FROM expense_reports').fetchone()[0]
        for changes in [{'vat_rate[]':['101','6']},{'category_id':'51'},{'date':'2026-02-30'},{'rate[]':['-1','10']},{'quantity[]':['0','1']},{'supplier':''},{'expense_type':'bad'}]:
            invalid=dict(data);invalid.update(changes)
            check(member.request('save.php',invalid)[0]==400,'Invalid expense accepted: '+str(changes))
        check(con.execute('SELECT COUNT(*) FROM expense_reports').fetchone()[0]==before,'Invalid submission mutated reports')
        # Save with decimal commas, verify rounded per-line taxes.
        edit=dict(data,id=rid);edit['rate[]']=['50,005','10'];
        check(member.request('save.php',edit)[0]==200,'Editing expense failed')
        check(con.execute('SELECT net_total,vat_total,total FROM expense_reports WHERE id=?',(rid,)).fetchone()==(110.01,21.6,131.61),'Rounding mismatch')
        status, _, url = member.request('duplicate.php',{'id':rid})
        check(status==200,'Duplicate failed')
        duplicate=int(url.split('id=')[1])
        check(con.execute('SELECT expense_type,supplier,net_total,vat_total,total FROM expense_reports WHERE id=?',(rid,)).fetchone()==con.execute('SELECT expense_type,supplier,net_total,vat_total,total FROM expense_reports WHERE id=?',(duplicate,)).fetchone(),'Duplicate lost VAT/type')
        reimbursement=dict(data, expense_type='reimbursement', recipient='Test recipient', address='Test address',iban='BE00 0000 0000 0000',bank_name='Demo bank')
        status, _, url=member.request('save.php',reimbursement)
        check(status==200,'Reimbursement creation failed'); reimbursement_id=int(url.split('id=')[1])
        # Upload a PNG (existing UI advertised image upload but server previously rejected it).
        boundary='flowexpense-test-boundary'
        png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9o0AAAAASUVORK5CYII=')
        multipart=(f'--{boundary}\r\nContent-Disposition: form-data; name="report_id"\r\n\r\n{rid}\r\n--{boundary}\r\nContent-Disposition: form-data; name="files[]"; filename="receipt.png"\r\nContent-Type: image/png\r\n\r\n').encode()+png+f'\r\n--{boundary}--\r\n'.encode()
        check(member.request('upload-attachment.php',multipart,{'Content-Type':f'multipart/form-data; boundary={boundary}'})[0]==200,'Image upload failed')
        attachment=con.execute('SELECT id,stored_name FROM expense_attachments WHERE report_id=?',(rid,)).fetchone()
        check(attachment is not None,'Image attachment not registered')
        check(member.request(f'file.php?id={attachment[0]}')[1]==png,'Attachment download changed content')
        status, archive, _=member.request('accounting-export.php')
        check(status==200,'Accounting export failed')
        z=zipfile.ZipFile(io.BytesIO(archive))
        rows=list(csv.reader(io.StringIO(z.read('expenses.csv').decode('utf-8-sig')),delimiter=';'))
        check(rows[0][8:11]==['Montant HT','Montant de TVA','Montant TTC'],'CSV headers not translated')
        check(any(name.endswith('receipt.png') for name in z.namelist()),'ZIP missing receipt')
        check(len(rows)==5,'Accounting export should include four member expenses')
        check(any(row[7].startswith("'=SUM") for row in rows[1:]),'CSV formula-like text was not escaped')
        for kind in ['business','reimbursement']:
            status, filtered, _ = member.request('accounting-export.php?expense_type='+kind)
            filtered_rows=list(csv.reader(io.StringIO(zipfile.ZipFile(io.BytesIO(filtered)).read('expenses.csv').decode('utf-8-sig')),delimiter=';'))
            check(status==200 and len(filtered_rows)==3,'Expense type filter failed')
        for export in ['export.php','export-xlsx.php']:
            status, artifact, _=member.request(f'{export}?id={rid}')
            check(status==200 and artifact.startswith(b'%PDF' if export=='export.php' else b'PK'),f'{export} failed')
            (work/('preview.pdf' if export=='export.php' else 'preview.xlsx')).write_bytes(artifact)
            if export=='export-xlsx.php':
                xlsx=zipfile.ZipFile(io.BytesIO(artifact));strings=xlsx.read('xl/sharedStrings.xml')
                check('Montant TTC'.encode() in strings,'XLSX missing translated VAT columns')
        for endpoint in ['form.php','export.php','export-xlsx.php']:
            check(other.request(f'{endpoint}?id={rid}')[0] in (403,404),'Cross-organization access allowed: '+endpoint)
        foreign=Client(base)
        con.execute("INSERT INTO user_organizations VALUES (11,1,'member')");con.commit()
        foreign.login('other@example.com','integration-only-password')
        foreign.request('switch-organization.php',{'organization_id':'1'})
        for endpoint in ['form.php','export.php','export-xlsx.php']:
            check(foreign.request(f'{endpoint}?id={rid}')[0]==403,'Cross-user access allowed: '+endpoint)
        check(foreign.request(f'file.php?id={attachment[0]}')[0]==403,'Cross-user attachment allowed')
        check(foreign.request('accounting-export.php')[0]==404,'Export leaked another member’s expenses')
        check(member.request('save.php',data,csrf=False)[0]==403,'CSRF-free mutation accepted')
        check(member.request(f'duplicate.php?id={rid}')[0]==405,'GET duplicated an expense')
        check(member.request('index.php?q=Supplier')[0]==200,'Search failed')
        check(b'LEGACY-100' not in member.request('index.php?q=Supplier')[1],'Search did not filter records')
        check(member.request('index.php?month=2026-99')[0]==400,'Invalid summary month accepted')
        check(b'Bilan mensuel' in member.request('index.php?month=2026-10')[1],'Monthly overview missing')
        check(member.request('history.php')[0]==200,'History failed')
        check(con.execute("SELECT COUNT(*) FROM audit_events WHERE report_id=? AND action='expense_updated'",(rid,)).fetchone()[0]==1,'Edit history not recorded')
        # Soft-delete expense and attachment without deleting the receipt bytes.
        check(member.request('delete.php',{'id':rid})[0]==200,'Soft delete failed')
        check(con.execute('SELECT deleted_at FROM expense_reports WHERE id=?',(rid,)).fetchone()[0] is not None,'Soft delete timestamp missing')
        check(member.request(f'form.php?id={rid}')[0]==404,'Trashed expense still editable')
        check((work/'attachments'/attachment[1]).exists(),'Soft delete removed receipt file')
        check(member.request('trash.php',{'id':rid})[0]==200,'Expense restore failed')
        check(con.execute('SELECT deleted_at FROM expense_reports WHERE id=?',(rid,)).fetchone()[0] is None,'Expense remained in trash')
        check(member.request('delete-attachment.php',{'id':attachment[0]})[0]==200,'Attachment soft delete failed')
        check(member.request(f'file.php?id={attachment[0]}')[0]==404,'Trashed attachment remained readable')
        check(member.request('trash.php',{'kind':'attachment','id':attachment[0]})[0]==200,'Attachment restore failed')
        check(member.request(f'file.php?id={attachment[0]}')[1]==png,'Restored receipt bytes changed')
        # Backup archive is private, complete and restorable.
        check(member.request('backups.php')[0]==403,'Member accessed all-application backups')
        backup_status,backup_body,_=admin.request('backups.php',{'action':'create'})
        check(backup_status==200,'Manual backup failed')
        names=sorted((work/'backups').glob('backup-*-manual-*.zip'))
        check(bool(names),'Manual archive missing: '+backup_body.decode()[-4500:])
        backup_name=names[-1].name
        status,raw,_=admin.request('backups.php?download='+backup_name)
        backup=zipfile.ZipFile(io.BytesIO(raw));manifest=json.loads(backup.read('manifest.json'))
        check(status==200 and 'expenses.db' in manifest['files'] and 'attachments/'+attachment[1] in manifest['files'],'Backup omitted database or receipt')
        check(admin.request('backups.php',{'action':'restore','backup':backup_name,'password':'wrong','confirmation':'RESTORE'})[0]==200,'Restore rejection page failed')
        check(con.execute('SELECT total FROM expense_reports WHERE id=?',(rid,)).fetchone()[0]==131.61,'Rejected restore changed data')
        # Exercise actual full restore through CLI on disposable storage.
        con.execute('UPDATE expense_reports SET total=999 WHERE id=?',(rid,));con.commit();con.close()
        subprocess.run(['php',str(ROOT/'tools/restore.php'),backup_name,'--confirm'],env=env,check=True,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
        con=sqlite3.connect(database)
        check(con.execute('SELECT total FROM expense_reports WHERE id=?',(rid,)).fetchone()[0]==131.61,'Full restoration did not recover database')
        check((work/'attachments'/attachment[1]).read_bytes()==png,'Full restoration did not recover receipt')
        check(bool(list((work/'backups').glob('backup-*-before-restore-*.zip'))),'Safety backup missing')
        # Existing sessions are invalidated by restoration; sign in again for remaining checks.
        check(b'Bienvenue' in member.request('index.php')[1],'Restoration left old session active')
        member.login('member@example.com','integration-only-password')
        foreign.login('other@example.com','integration-only-password')
        foreign.request('switch-organization.php',{'organization_id':'1'})
        # Missing receipts fail the complete export explicitly.
        (work/'attachments'/attachment[1]).unlink()
        check(member.request('accounting-export.php')[0]==500,'Missing attachment silently omitted')
        check(member.request('accounting-export.php?from=2026-12-01&to=2026-01-01')[0]==400,'Reversed date range allowed')
        # Reject disguised HTML uploads even with an allowed image extension.
        disguised=multipart.replace(png,b'<html><script>alert(1)</script></html>')
        member.request('upload-attachment.php',disguised,{'Content-Type':f'multipart/form-data; boundary={boundary}'})
        check(con.execute('SELECT COUNT(*) FROM expense_attachments WHERE report_id=?',(rid,)).fetchone()[0]==1,'Disguised HTML image was accepted')
        denied=dict(data,id=rid,category_id='')
        check(foreign.request('save.php',denied)[0]==403,'A member edited another member’s expense')
        check(not con.execute('PRAGMA foreign_key_check').fetchall(),'Foreign key integrity failed')
        check(con.execute('SELECT total,net_total,vat_total FROM expense_reports WHERE id=100').fetchone()==(19.95,19.95,0),'Legacy totals changed during requests')
        # Catalog completeness and supported languages.
        catalog=json.loads((PUBLIC/'lib/translations.json').read_text())
        check(all(set(value)=={'fr','en','nl'} and all(value.values()) for value in catalog.values()),'Incomplete translation catalog')
        if os.environ.get('FLOWEXPENSE_TEST_ARTIFACTS'):
            target=Path(os.environ['FLOWEXPENSE_TEST_ARTIFACTS']);target.mkdir(parents=True,exist_ok=True)
            for name in ['preview.pdf','preview.xlsx']:
                shutil.copy(work/name,target/name)
        print(f'{checks} checks passed: migration, languages, access, CSRF, search, monthly overview, trash, history, backup/restore, VAT, uploads and exports.')
    finally:
        process.terminate();process.wait(timeout=10);log.close();con.close()
        text=(work/'server.log').read_text()
        serious=[line for line in text.splitlines() if any(term in line for term in ['Fatal error','Warning:','Parse error'])]
        if serious: print('\n'.join(serious));raise RuntimeError('PHP server logged warnings/errors')
