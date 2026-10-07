#!/usr/bin/env python3
"""Real accounts and guests share one URL, never a database or authentication state."""
import os,re,time,socket,sqlite3,tempfile,subprocess,urllib.request,urllib.error,urllib.parse,http.cookiejar,io,zipfile
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
 def req(self,path,data=None,csrf=True):
  headers={}
  if data is not None:
   if csrf:headers['X-CSRF-Token']=self.token
   data=urllib.parse.urlencode(data,doseq=True).encode()
  try:r=self.opener.open(urllib.request.Request(self.base+'/'+path,data=data,headers=headers),timeout=30)
  except urllib.error.HTTPError as e:r=e
  body=r.read();tokens=re.findall(rb'name="csrf_token" value="([a-f0-9]+)"',body)
  if tokens:self.token=tokens[0].decode()
  return r.status,body
with tempfile.TemporaryDirectory(prefix='flowexpense-mixed-') as folder:
 work=Path(folder);guests=work/'guests';guests.mkdir();private=work/'private';private.mkdir();sessions=work/'sessions';sessions.mkdir()
 env=os.environ.copy();env.update(GUEST_ENABLED='1',FLOWEXPENSE_DEMO='0',DEMO_STORAGE_ROOT=str(guests),FLOWEXPENSE_ENV_FILE=str(work/'missing.env'),EXPENSE_DB_PATH=str(private/'expenses.db'),APP_ENV='development',APP_HTTPS='0',BACKUP_AUTOMATIC='0',ADMIN_EMAIL='owner@example.invalid',ADMIN_PASSWORD='test-only-account-password',ADMIN_NAME='Private Owner')
 sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base=f'http://127.0.0.1:{port}'
 log=open(work/'server.log','w+');proc=subprocess.Popen(['php','-d','display_errors=0','-d','session.save_path='+str(sessions),'-S',f'127.0.0.1:{port}','-t',str(ROOT/'public')],env=env,stdout=log,stderr=log)
 try:
  owner=Client(base);guest=Client(base);second=Client(base)
  for _ in range(50):
   try:status,body=owner.req('login.php');break
   except urllib.error.URLError:time.sleep(.1)
  check(status==200 and b'name="email"' in body and b'action="guest.php"' in body,'Same login must offer credentials and local guest entry')
  check(not list(guests.glob('guest-*')),'Login silently created a guest workspace')
  status,body=owner.req('login.php',{'email':'owner@example.invalid','password':'test-only-account-password'})
  check(status==200 and b'Private Owner' in body and b'nav-locked' not in body,'Real admin login failed')
  con=sqlite3.connect(private/'expenses.db');org=con.execute('SELECT id FROM organizations').fetchone()[0]
  con.execute('INSERT INTO expense_reports(organization_id,user_id,custom_id,supplier,description,date,total,net_total,expense_type) VALUES(?,1,?,?,?,?,?,?,?)',(org,'REAL-SECRET','Private-only Supplier','Private-only description','2026-10-06',100,100,'business'));con.commit()
  check(b'Private-only Supplier' in owner.req('index.php')[1],'Real account cannot see its data')
  guest.req('login.php');check(guest.req('guest.php',{},csrf=False)[0]==403,'Guest entry missing CSRF protection')
  status,body=guest.req('guest.php',{})
  check(status==200 and b'DEMO-0001' in body and b'Private-only' not in body,'Guest leaked private data')
  check(body.count(b'aria-disabled="true"')==4,'Guest locks missing')
  check('Me connecter à mon compte'.encode() in body,'Guest cannot return to account sign-in')
  first=list(guests.glob('guest-*'))[0];guestcon=sqlite3.connect(first/'expenses.db')
  check(guestcon.execute('SELECT SUM(is_admin) FROM users').fetchone()[0]==0,'Guest is admin')
  for route in ['users.php','settings.php','backups.php','organizations.php']:
   check(guest.req(route)[0]==403,'Guest can access '+route)
   check(owner.req(route)[0]==200,'Real admin cannot access '+route)
  guestcon.execute('INSERT INTO expense_reports(organization_id,user_id,custom_id,supplier,description,date,total,net_total,expense_type) VALUES(1,1,?,?,?,?,?,?,?)',('GUEST-ONLY','Guest-only supplier','Demo example','2026-10-06',42,42,'business'));guestcon.commit()
  check(b'Guest-only supplier' in guest.req('index.php')[1],'Guest data missing')
  check(b'Guest-only supplier' not in owner.req('index.php')[1],'Private account received guest data')
  check(b'Private Owner' not in guest.req('login.php',{'email':'owner@example.invalid','password':'test-only-account-password'})[1],'Guest switched to real identity without leaving workspace')
  check(guest.req('logout.php',{},csrf=False)[0]==403,'Logout missing CSRF')
  status,body=guest.req('logout.php',{})
  check(status==200 and b'name="email"' in body and b'action="guest.php"' in body,'Guest logout did not return to combined login')
  status,body=guest.req('login.php',{'email':'owner@example.invalid','password':'test-only-account-password'})
  check(status==200 and b'Private-only Supplier' in body and b'Guest-only supplier' not in body,'Guest-to-real account leaked or lost data')
  check(guest.req('settings.php')[0]==200,'Guest lock persisted after real admin login')
  # Switching a logged-in real account into a guest clears the real authentication state.
  status,body=owner.req('guest.php',{})
  check(status==200 and b'DEMO-0001' in body and b'Private-only' not in body,'Real-to-guest switch retained real identity')
  check(owner.req('users.php')[0]==403,'Real privileges leaked into guest workspace')
  check(con.execute('SELECT COUNT(*) FROM users WHERE is_admin=1').fetchone()[0]==1,'Guest changed real users')
  check(con.execute('SELECT COUNT(*) FROM expense_reports').fetchone()[0]==1,'Guest changed real reports')
  before_second=set(guests.glob('guest-*'));second.req('login.php');check(second.req('guest.php',{})[0]==200,'Second guest failed')
  check(len(list(guests.glob('guest-*')))==3,'Independent visitors share workspaces')
  status,body=second.req('accounting-export.php');z=zipfile.ZipFile(io.BytesIO(body));check(any(n.endswith('.pdf') for n in z.namelist()),'Real accounting export missing guest receipts')
  current=next(p for p in guests.glob('guest-*') if p not in before_second)
  (current/'created-at').write_text(str(int(time.time())-86401))
  status,body=second.req('index.php');check(status==200 and b'name="email"' in body,'Expired guest did not return to combined login')
  check(b'Private-only' not in body,'Guest expiry exposed real data')
  print(f'{checks} mixed-access checks passed: one login, real account access, guest isolation, privileges, transitions, CSRF, exports and expiration.')
 except Exception:
  log.flush();print((work/'server.log').read_text()[-3000:]);raise
 finally:proc.terminate();proc.wait(timeout=10);log.close()
