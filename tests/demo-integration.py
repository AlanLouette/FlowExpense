#!/usr/bin/env python3
"""Guest isolation and actual application routes; all storage is disposable."""
import os,re,time,socket,sqlite3,tempfile,subprocess,urllib.request,urllib.error,urllib.parse,http.cookiejar,zipfile,io,json
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
checks=0
def check(value,message):
 global checks
 assert value,message
 checks+=1
class Client:
 def __init__(self,base):
  self.base=base;self.token='';self.jar=http.cookiejar.CookieJar();self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
 def req(self,path='',data=None,csrf=True):
  headers={}
  if data is not None:
   if csrf:headers['X-CSRF-Token']=self.token
   data=urllib.parse.urlencode(data,doseq=True).encode()
  request=urllib.request.Request(self.base+'/'+path,data=data,headers=headers)
  try:r=self.opener.open(request,timeout=30)
  except urllib.error.HTTPError as e:r=e
  body=r.read();tokens=re.findall(rb'name="csrf_token" value="([a-f0-9]+)"',body)
  if tokens:self.token=tokens[0].decode()
  return r.status,body
with tempfile.TemporaryDirectory(prefix='flowexpense-guests-') as folder:
 work=Path(folder);storage=work/'guests';storage.mkdir();sessions=work/'sessions';sessions.mkdir();canary=work/'private.db';canary.write_bytes(b'private-data-must-never-open')
 env=os.environ.copy();env.update(FLOWEXPENSE_DEMO='1',DEMO_STORAGE_ROOT=str(storage),FLOWEXPENSE_ENV_FILE=str(work/'missing.env'),EXPENSE_DB_PATH=str(canary),APP_ENV='development',APP_HTTPS='0',BACKUP_AUTOMATIC='1')
 sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base=f'http://127.0.0.1:{port}'
 log=open(work/'server.log','w+')
 proc=subprocess.Popen(['php','-d','display_errors=0','-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','-t',str(ROOT/'public')],env=env,stdout=log,stderr=log)
 try:
  a=Client(base);b=Client(base)
  for _ in range(50):
   try:status,body=a.req('index.php');break
   except urllib.error.URLError:time.sleep(.1)
  check(status==200 and 'Essayer en invité'.encode() in body,'Guest landing missing')
  check(not list(storage.glob('guest-*')),'Landing created a database')
  check(a.req('guest.php',{},csrf=False)[0]==403,'Guest created without CSRF')
  check(not list(storage.glob('guest-*')),'Bad CSRF allocated storage')
  status,body=a.req('guest.php',{'language':'fr'})
  check(status==200 and b'Guest' in body and b'DEMO-0001' in body,'Guest initialization failed')
  check(body.count(b'aria-disabled="true"')==4,'Guest administration menus not visibly locked')
  check(b'nav-group-title' in body,'Navigation groups missing')
  first=list(storage.glob('guest-*'))[0];con=sqlite3.connect(first/'expenses.db')
  check(con.execute('SELECT COUNT(*) FROM expense_reports').fetchone()[0]==5,'Missing fictional expenses')
  check(con.execute('SELECT COUNT(*) FROM expense_attachments').fetchone()[0]==5,'Missing PDF receipts')
  check(con.execute('SELECT SUM(is_admin) FROM users').fetchone()[0]==0,'Guest has admin privileges')
  check(canary.read_bytes()==b'private-data-must-never-open','Private database touched')
  for route in ['settings.php','users.php','organizations.php','backups.php']:
   check(a.req(route)[0]==403,'Guest can administer '+route)
  for route in ['form.php?id=1','export.php?id=1','export-xlsx.php?id=1','accounting-export.php','trash.php','history.php','categories.php','units.php','recipients.php']:
   status,body=a.req(route);check(status==200,'Real route failed '+route)
   if route.startswith('export.php'):check(body.startswith(b'%PDF'),'Invalid expense PDF')
   if route.startswith('export-xlsx'):check(zipfile.is_zipfile(io.BytesIO(body)),'Invalid XLSX')
  status,body=a.req('file.php?id=1');check(status==200 and body.startswith(b'%PDF'),'Fictional receipt unavailable')
  b.req('index.php');check(b.req('guest.php',{'language':'en'})[0]==200,'Second guest failed')
  second=next(p for p in storage.glob('guest-*') if p!=first)
  check(second!=first,'Guests share storage')
  payload={'expense_type':'business','supplier':'Guest A only','recipient':'','address':'','description':'Private guest example','iban':'','bank_name':'','date':'2026-10-06','category_id':'','description_line[]':['Example'],'quantity[]':['1'],'unit[]':['pièce'],'rate[]':['100'],'vat_rate[]':['21']}
  status,body=a.req('save.php',payload);check(status==200,'Guest cannot save a real expense: '+body.decode(errors='replace')[:300])
  check(con.execute('SELECT total FROM expense_reports WHERE supplier=?',('Guest A only',)).fetchone()[0]==121,'Guest VAT incorrect')
  check(b'Guest A only' not in b.req('index.php')[1],'Guest A leaked to guest B')
  check(b.req('form.php?id=6')[0]==404,'Guest B read guest A report')
  check(b'Guest A only' in a.req('index.php')[1],'Guest changes not retained')
  check(a.req('duplicate.php',{'id':'1'},csrf=False)[0]==403,'Guest CSRF bypass')
  check(a.req('duplicate.php',{'id':'1'})[0]==200,'Guest duplication failed')
  status,body=a.req('accounting-export.php');z=zipfile.ZipFile(io.BytesIO(body));check(any(n.endswith('.pdf') for n in z.namelist()),'Accounting export missing receipts')
  check(a.req('language.php',{'language':'nl','return':'index.php'})[0]==200,'Guest language switch failed')
  check(b'<html lang="nl"' in a.req('index.php')[1],'Guest language not retained')
  othercon=sqlite3.connect(second/'expenses.db')
  for i in range(95):othercon.execute('INSERT INTO expense_reports(organization_id,user_id,custom_id,total) VALUES(1,1,?,0)',('LIMIT-'+str(i),))
  othercon.commit();check(b.req('duplicate.php',{'id':'1'})[0]==413,'Demo report cap bypassed')
  othercon.execute("DELETE FROM expense_reports WHERE custom_id LIKE 'LIMIT-%'");othercon.commit();othercon.close()
  (second/'operations').write_text('600');check(b.req('update-status.php',{'id':'1','status':'Open'})[0]==413,'Demo operation cap bypassed');(second/'operations').write_text('0')
  old=list(storage.glob('guest-*'));check(a.req('guest.php',{'action':'reset'})[0]==200,'Guest reset failed')
  check(not first.exists(),'Old guest workspace retained after reset')
  check(b'Guest A only' not in a.req('index.php')[1],'Reset retained old guest data')
  check(second.exists(),'Reset deleted another guest')
  expired=next(p for p in storage.glob('guest-*') if p!=second);(expired/'created-at').write_text(str(int(time.time())-86401))
  run=subprocess.run(['php',str(ROOT/'tools/demo-cleanup.php'),str(storage)],capture_output=True,text=True)
  check(run.returncode==0 and not expired.exists(),'Expired workspace not cleaned')
  check(second.exists(),'Cleanup deleted active workspace')
  check('Proberen als gast'.encode() in a.req('index.php')[1],'Expired visitor did not return to landing')
  check(a.req('db.php')[0]==404,'Internal DB endpoint accessible')
  check(canary.read_bytes()==b'private-data-must-never-open','Private database modified')
  check(not list(storage.rglob('backups')),'Public demo made backups')
  print(f'{checks} guest checks passed: isolation, fictional PDF receipts, real exports, mutations, CSRF, admin denial, reset, expiry and private DB preservation.')
 except Exception:
  log.flush();print((work/'server.log').read_text()[-6000:]);raise
 finally:
  proc.terminate();proc.wait(timeout=10);log.close()
