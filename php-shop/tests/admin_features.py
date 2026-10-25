"""Admin integration tests against a disposable DB and a real PHP HTTP server."""
import base64
import hashlib
import hmac
import http.cookiejar as cookies
import io
import os
from pathlib import Path
import re
import secrets
import socket
import sqlite3
import struct
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
import zipfile

root=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory() as tmp:
    password=secrets.token_urlsafe(20)
    env=dict(os.environ,SHOP_STORAGE=tmp+'/storage',SHOP_DB=tmp+'/test.sqlite',ADMIN_EMAIL='owner@example.test',ADMIN_PASSWORD=password,SHOP_URL='https://example.test',MAIL_FROM='no-reply@example.test')
    subprocess.run(['php',str(root/'bin/install.php')],env=env,check=True)
    # Migration must be repeatable and preserve existing accounts.
    for _ in range(2): subprocess.run(['php',str(root/'bin/migrate-stone.php')],env=env,check=True)
    with socket.socket() as s:
        s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
    base=f'http://127.0.0.1:{port}'
    def client(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies.CookieJar()))
    owner,editor,sales,visitor=client(),client(),client(),client()
    def http(c,path='/',fields=None,files=None):
        data=None; headers={}
        if files:
            boundary='test'+secrets.token_hex(12); chunks=[]
            for key,value in (fields or {}).items(): chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            for key,(name,mime,content) in files.items(): chunks.extend([f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode(),content,b'\r\n'])
            chunks.append(f'--{boundary}--\r\n'.encode()); data=b''.join(chunks); headers['Content-Type']='multipart/form-data; boundary='+boundary
        elif fields is not None: data=urllib.parse.urlencode(fields).encode()
        request=urllib.request.Request(base+path,data=data,headers=headers)
        try:
            with c.open(request,timeout=15) as r: return r.status,r.read(),dict(r.headers)
        except urllib.error.HTTPError as ex: return ex.code,ex.read(),dict(ex.headers)
    def get(c,path='/'): return http(c,path)[1].decode()
    def post(c,action,fields=None,files=None,path='/?page=admin'):
        token=re.search(r'name="csrf" value="([^"]+)"',get(c,'/?page=login'))[1]
        return http(c,path,dict(fields or {},action=action,csrf=token),files)
    def scalar(sql,args=()):
        with sqlite3.connect(env['SHOP_DB']) as db: return db.execute(sql,args).fetchone()[0]
    def execute(sql,args=()):
        with sqlite3.connect(env['SHOP_DB']) as db: return db.execute(sql,args).lastrowid
    def totp(secret,counter=None):
        if counter is None: counter=int(time.time())//30
        secret+='='*((8-len(secret)%8)%8); digest=hmac.new(base64.b32decode(secret),struct.pack('>Q',counter),hashlib.sha1).digest(); off=digest[-1]&15
        return str((struct.unpack('>I',digest[off:off+4])[0]&0x7fffffff)%1000000).zfill(6)
    with open(tmp+'/server.log','w+') as log:
        server=subprocess.Popen(['php','-d','session.save_path='+tmp,'-d','upload_max_filesize=50M','-d','post_max_size=55M','-S',f'127.0.0.1:{port}','-t',str(root/'public')],env=env,stdout=log,stderr=log)
        try:
            for _ in range(50):
                try:
                    if http(visitor)[0]==200: break
                except OSError: time.sleep(.1)
            else: raise AssertionError('server not ready')
            assert post(owner,'login',dict(email=env['ADMIN_EMAIL'],password=password))[0]==200
            for tab in ['media','content','users','audit','backup','notifications','categories']:
                assert http(owner,'/?page=admin&tab='+tab)[0]==200,tab
            for c,level in [(editor,'editor'),(sales,'sales')]:
                assert post(owner,'staff_save',dict(id=0,name=level,email=level+'@example.test',password='test-staff-password-123',access_level=level,active=1))[0]==200
                assert post(c,'login',dict(email=level+'@example.test',password='test-staff-password-123'))[0]==200
            assert http(editor,'/?page=admin&tab=backup')[0]==403
            assert http(sales,'/?page=admin&tab=content')[0]==403
            assert post(editor,'site_settings',dict(brand='Forbidden'))[0]==403
            assert post(sales,'staff_save',dict(id=0))[0]==403
            assert 'Doanh thu' not in get(editor,'/?page=admin')
            assert post(owner,'staff_save',dict(id=1,name='Owner',email=env['ADMIN_EMAIL'],access_level='editor',active=1))[0]==200
            assert scalar('SELECT access_level FROM users WHERE id=1')=='owner'
            # Upload validation, serving and product gallery.
            png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6Zc8AAAAASUVORK5CYII=')
            status,body,_=post(editor,'media_upload',{},dict(image=('test.png','image/png',png)),path='/?page=admin&tab=media')
            assert status==200 and scalar('SELECT COUNT(*) FROM media')==1,body.decode()
            mid=scalar('SELECT id FROM media LIMIT 1')
            status,body,headers=http(visitor,'/media.php?id='+str(mid)); assert status==200 and body==png and headers['Content-Type']=='image/png'
            post(editor,'media_upload',{},dict(image=('bad.svg','image/svg+xml',b'<svg onload="alert(1)"></svg>')),path='/?page=admin&tab=media')
            assert scalar('SELECT COUNT(*) FROM media')==1
            post(editor,'gallery_add',dict(product_id=1,media_id=mid,sort_order=2)); assert '/media.php?id='+str(mid) in get(visitor,'/?page=product&id=1')
            # Category hierarchy cannot form a cycle or delete assigned categories.
            post(editor,'category_save',dict(id=0,name='Subcategory',parent_id=1,sort_order=4)); cid=scalar("SELECT id FROM categories WHERE name='Subcategory'")
            post(editor,'category_save',dict(id=1,name='Cycle',parent_id=cid,sort_order=0)); assert scalar('SELECT parent_id FROM categories WHERE id=1') is None
            post(editor,'category_delete',dict(id=1)); assert scalar('SELECT COUNT(*) FROM categories WHERE id=1')==1
            post(editor,'category_delete',dict(id=cid)); assert scalar('SELECT COUNT(*) FROM categories WHERE id=?',(cid,))==0
            # Publishing and removing content never exposes drafts or raw HTML.
            fields=dict(id=0,type='project',slug='test-project',title='Test project',body='Test body <script>alert(1)</script>',image='/media.php?id='+str(mid),status='draft',seo_title='Project SEO',seo_description='SEO description')
            post(editor,'content_save',fields); contentid=scalar("SELECT id FROM content WHERE slug='test-project'")
            assert http(visitor,'/?page=content&slug=test-project')[0]==404
            fields.update(id=contentid,status='published'); post(editor,'content_save',fields)
            html=get(visitor,'/?page=content&slug=test-project'); assert 'Project SEO' in html and '&lt;script&gt;' in html and '<script>alert(1)</script>' not in html
            assert 'Test project' in get(visitor,'/?page=projects')
            post(editor,'content_delete',dict(id=contentid)); assert http(visitor,'/?page=content&slug=test-project')[0]==404
            # Quote calculations and print view exclude confidential notes.
            post(visitor,'quote',dict(product_id=1,name='Buyer',phone='0901234567',material='White',dimensions='120 cm',location='Da Nang',notes='Test',consent=1),path='/?page=quote')
            qid=scalar('SELECT id FROM quote_requests LIMIT 1')
            post(sales,'quote_line_add',dict(quote_id=qid,description='Sculpture',qty=2,price=1000000))
            post(sales,'quote_detail',dict(quote_id=qid,shipping=200000,deposit=500000,valid_until='2027-01-01',internal_notes='CONFIDENTIAL NOTE'))
            html=get(sales,'/?page=quote_print&id='+str(qid)); assert '2.200.000' in html and 'CONFIDENTIAL NOTE' not in html
            assert http(editor,'/?page=quote_print&id='+str(qid))[0]==403
            post(sales,'quote_detail',dict(quote_id=qid,shipping=200000,deposit=9000000,valid_until='',internal_notes='X'))
            assert scalar('SELECT deposit FROM quote_requests WHERE id=?',(qid,))==500000
            # Password reset tokens are hashed, expire and are single use. 2FA remains independent.
            post(visitor,'forgot',dict(email='sales@example.test'),path='/?page=forgot')
            mailbody=scalar("SELECT body FROM notifications WHERE subject='Đặt lại mật khẩu' ORDER BY id DESC LIMIT 1")
            reset_token=mailbody.rsplit('=',1)[1]
            assert scalar('SELECT COUNT(*) FROM password_resets WHERE token_hash=?',(reset_token,))==0
            post(visitor,'reset',dict(reset_token=reset_token,password='reset-password-12345'),path='/?page=reset')
            assert scalar('SELECT COUNT(*) FROM password_resets')==0
            result=post(visitor,'reset',dict(reset_token=reset_token,password='other-password-123'),path='/?page=reset')
            assert 'Liên kết không hợp lệ' in result[1].decode()
            assert http(sales,'/?page=admin')[0]==200 and 'Đăng nhập' in get(sales,'/?page=admin')
            # Enroll TOTP, reject a replay, then use a fresh step in an independent login.
            post(editor,'totp_start',dict(current_password='test-staff-password-123'),path='/?page=security')
            secret=re.search(r'readonly value="([A-Z2-7]+)"',get(editor,'/?page=security'))[1]
            enrolled_code=totp(secret)
            post(editor,'totp_enable',dict(code=enrolled_code),path='/?page=security')
            assert scalar("SELECT totp_secret FROM users WHERE email='editor@example.test'")==secret
            second=client(); post(second,'login',dict(email='editor@example.test',password='test-staff-password-123'),path='/?page=login')
            assert 'Xác thực hai bước' in get(second,'/?page=twofactor')
            post(second,'totp_login',dict(code=enrolled_code),path='/?page=twofactor')
            assert 'Mã xác thực không đúng' in get(second,'/?page=twofactor') or 'Đăng nhập' in get(second,'/?page=admin')
            # Set last step behind current to exercise success without sleeping 30 seconds.
            execute("UPDATE users SET totp_last=? WHERE email='editor@example.test'",(int(time.time())//30-2,))
            assert post(second,'totp_login',dict(code=totp(secret)),path='/?page=twofactor')[0]==200
            assert 'Thư viện ảnh' in get(second,'/?page=admin&tab=media')
            # A new password login must not reset the account-level 2FA lockout.
            locked=client(); post(locked,'login',dict(email='editor@example.test',password='test-staff-password-123'),path='/?page=login')
            wrong=str((int(totp(secret))+1)%1000000).zfill(6)
            for _ in range(5): post(locked,'totp_login',dict(code=wrong),path='/?page=twofactor')
            post(locked,'login',dict(email='editor@example.test',password='test-staff-password-123'),path='/?page=login')
            result=post(locked,'totp_login',dict(code=totp(secret)),path='/?page=twofactor')
            assert 'khóa 15 phút' in result[1].decode()
            # Schema-mismatched SQLite cannot replace the active database.
            invalid_db=tmp+'/invalid.sqlite'
            with sqlite3.connect(invalid_db) as db: db.execute('CREATE TABLE example(id INTEGER)')
            result=post(owner,'backup_restore',dict(current_password=password,confirm='KHOI PHUC'),dict(backup=('invalid.sqlite','application/octet-stream',Path(invalid_db).read_bytes())),path='/?page=admin&tab=backup')
            assert 'khác phiên bản' in result[1].decode()
            # Backup archive contains DB and media. Restoring revokes sessions and validates archive paths.
            status,backup,_=post(owner,'backup_bundle'); assert status==200 and backup[:2]==b'PK',backup[:200]
            with zipfile.ZipFile(io.BytesIO(backup)) as archive: assert 'database.sqlite' in archive.namelist() and len(archive.namelist())==2
            bad=io.BytesIO()
            with zipfile.ZipFile(bad,'w') as archive: archive.writestr('../escape.txt','bad')
            post(owner,'backup_restore',dict(current_password=password,confirm='KHOI PHUC'),dict(backup=('bad.zip','application/zip',bad.getvalue())),path='/?page=admin&tab=backup')
            assert scalar("SELECT COUNT(*) FROM users WHERE role='admin'")==3
            post(owner,'backup_restore',dict(current_password=password,confirm='KHOI PHUC'),dict(backup=('backup.zip','application/zip',backup)),path='/?page=admin&tab=backup')
            assert 'Đăng nhập' in get(owner,'/?page=admin')
            assert 'Đăng nhập' in get(second,'/?page=admin')
            assert scalar("SELECT COUNT(*) FROM audit_log WHERE event='backup.restored'")==1
            assert scalar('SELECT COUNT(*) FROM password_resets')==0
            print('PASS admin: media validation/gallery, hierarchy, CMS draft/publish/XSS, roles, quote totals/print, reset tokens, TOTP replay/login, ZIP backup/restore and session revocation')
        finally:
            server.terminate(); server.wait(timeout=5)
            log.seek(0); output=log.read()
            if 'Fatal error' in output or 'Uncaught' in output: print(output)
