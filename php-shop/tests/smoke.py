"""End-to-end checks using isolated SQLite DB and a real PHP HTTP server."""
import http.cookiejar
import os
from pathlib import Path
import re
import secrets
import socket
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
                    with c.open(base+path, None if data is None else urllib.parse.urlencode(data).encode()) as r:
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
            html=request(buyer)[1]
            assert '&lt;script&gt;alert(1)&lt;/script&gt;' in html and '<script>alert(1)</script>' not in html
            print('PASS HTTP: CSRF, registration, roles, cart, checkout, order ownership, admin pages, cancellation, XSS escaping')
        finally:
            server.terminate()
            server.wait(timeout=5)
            log.seek(0)
            errors=log.read()
            if "Fatal error" in errors:
                print(errors)
