"""End-to-end checks using isolated SQLite DB and a real PHP HTTP server."""
import http.cookiejar
import os
from pathlib import Path
import re
import secrets
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

root = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory() as tmp:
    env = dict(os.environ, SHOP_DB=tmp+'/test.sqlite', ADMIN_EMAIL='admin@example.test', ADMIN_PASSWORD=secrets.token_urlsafe(20))
    subprocess.run(['php', str(root/'bin/install.php')], env=env, check=True)
    with sqlite3.connect(env['SHOP_DB']) as db:
        db.execute('UPDATE products SET price=590000,stock=30 WHERE id=1')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    with open(tmp+'/server.log', 'w+') as log:
        server = subprocess.Popen(['php', '-d', 'session.save_path='+tmp, '-S', f'127.0.0.1:{port}', '-t', str(root/'public')], env=env, stdout=log, stderr=log)
        try:
            def client():
                return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
            buyer, other, admin = client(), client(), client()
            def request(c, path='/', data=None):
                try:
                    with c.open(base+path, None if data is None else urllib.parse.urlencode(data).encode(), timeout=10) as r:
                        return r.status, r.read().decode(), r.url
                except urllib.error.HTTPError as ex:
                    return ex.code, ex.read().decode(), ex.url
            for _ in range(50):
                try:
                    assert request(buyer)[0] == 200
                    break
                except OSError:
                    time.sleep(.1)
            else:
                raise AssertionError('Server did not start')
            def post(c, action, fields, path='/'):
                status, html, _ = request(c, '/?page=login')
                token = re.search(r'name="csrf" value="([^"]+)"', html)[1]
                return request(c, path, dict(fields, action=action, csrf=token))
            result = request(buyer, '/', {'action':'register'}); assert result[0] == 419, result
            for c, name in [(buyer,'buyer'),(other,'other')]:
                assert post(c,'register',dict(name=name,email=name+'@example.test',password='password-123456'))[0] == 200
            assert request(buyer, '/?page=admin')[0] == 403
            assert post(buyer,'cart',dict(id=1,qty=2))[0] == 200
            status, html, url = post(buyer,'checkout',dict(name='Buyer',phone='0901234567',address='Ha Noi',payment='cod',coupon='WELCOME10'))
            assert status == 200 and '1.062.000' in html and 'page=order' in url
            orderpath = urllib.parse.urlsplit(url).query
            result=request(other, '/?'+orderpath); assert result[0] == 404, result
            assert post(admin,'login',dict(email=env['ADMIN_EMAIL'],password=env['ADMIN_PASSWORD']))[0] == 200
            for tab in ['dashboard','products','categories','orders','customers','coupons']:
                assert request(admin, '/?page=admin&tab='+tab)[0] == 200
            assert post(admin,'status',dict(id=1,status='cancelled'))[0] == 200
            assert post(admin,'product',dict(id=0,category_id=1,name='<script>alert(1)</script>',description='Test',price=10000,stock=10,image='',active=1))[0] == 200
            html=request(admin, '/?page=admin&tab=products')[1]
            assert '&lt;script&gt;alert(1)&lt;/script&gt;' in html and '<script>alert(1)</script>' not in html
            assert request(buyer, '/?page=quote')[0] == 200
            status, html, _ = post(buyer, 'quote', dict(product_id=1,name='Quote Buyer',phone='0901234567',material='Đá trắng',dimensions='Cao 120 cm',location='Đà Nẵng',notes='Cần tư vấn bệ tượng',consent=1), '/?page=quote')
            assert status == 200 and 'Đã nhận yêu cầu' in html
            html=request(admin, '/?page=admin&tab=quotes')[1]
            assert 'Quote Buyer' in html and 'Cần tư vấn bệ tượng' in html
            for route in ['artist','services','about','process','contact']:
                status, html, _ = request(buyer, '/?page='+route)
                assert status == 200 and 'Fatal error' not in html
            assert 'Người gửi hồn vào Đá' in re.sub(r'<[^>]+>', ' ', request(buyer)[1]).replace('vào  Đá', 'vào Đá')
            assert post(buyer,'site_settings',dict(brand='FORBIDDEN'))[0] == 403
            settings=dict(brand='Nghệ nhân Văn Nho',phone='0901234567',address='Địa chỉ kiểm thử',hero_image='',portrait_image='',work_image_1='',work_image_2='',work_image_3='')
            assert post(admin,'site_settings',settings)[0] == 200
            assert '0901234567' in request(buyer, '/?page=contact')[1]
            with sqlite3.connect(env['SHOP_DB']) as db:
                assert db.execute('SELECT value FROM site_settings WHERE key=?', ('brand',)).fetchone()[0] == settings['brand']
            invalid=dict(settings,hero_image='javascript:alert(1)')
            status, html, _ = post(admin,'site_settings',invalid)
            assert 'URL HTTPS hợp lệ' in html
            assert request(buyer, '/?q%5B%5D=bad')[0] == 400
            assert request(buyer, '/', {'csrf[]':'bad','action':'quote'})[0] == 400
            home=request(buyer)[1]
            assert '/assets/stone.css?v=' in home and 'mobile-menu' in home and 'skip-link' in home
            assert '<title>Tượng Quan Âm' in request(buyer, '/?page=product&id=1')[1]
            assert 'noindex,follow' in request(buyer, '/?q=Quan')[1]
            with buyer.open(base+'/', timeout=10) as response:
                assert 'no-store' in response.headers.get('Cache-Control','')
            status, html, _=post(buyer,'profile',dict(name='MUST NOT SAVE',current_password='wrong',password='new-password-12345'), '/?page=account')
            with sqlite3.connect(env['SHOP_DB']) as db:
                assert db.execute('SELECT name FROM users WHERE email=?', ('buyer@example.test',)).fetchone()[0] == 'buyer'
                for i in range(27):
                    db.execute('INSERT INTO quote_requests(name,phone,material,dimensions,location,notes,status) VALUES(?,?,?,?,?,?,?)', ('Pagination '+str(i),'0900000000','Đá trắng','100 cm','Đà Nẵng','Test', 'quoted' if i<2 else 'new'))
            html=request(admin, '/?page=admin&tab=quotes')[1]
            assert html.count('<article class="coupon">') == 25 and 'Trang 1/2' in html
            html=request(admin, '/?page=admin&tab=quotes&n=2')[1]
            assert html.count('<article class="coupon">') == 3 and 'Trang 2/2' in html
            html=request(admin, '/?page=admin&tab=quotes&status=quoted&q=Pagination')[1]
            assert html.count('<article class="coupon">') == 2
            assert 'Không có yêu cầu phù hợp' in request(admin, '/?page=admin&tab=quotes&q=%25')[1]
            print('PASS HTTP: CSRF, registration, roles, cart, checkout, order ownership, admin pages, cancellation, XSS escaping, quote requests, artist pages, settings permissions, malformed inputs, atomic profile, metadata, quote pagination/filter')
        finally:
            server.terminate()
            server.wait(timeout=5)
            log.seek(0)
            errors=log.read()
            if "Fatal error" in errors:
                print(errors)
