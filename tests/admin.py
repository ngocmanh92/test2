"""Authentication, CSRF, draft visibility and publish persistence checks."""
import http.cookiejar, re, urllib.request, urllib.parse, urllib.error
from pathlib import Path
base = 'http://127.0.0.1:8080/'
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(path, data=None):
    try:
        response = opener.open(base + path, urllib.parse.urlencode(data).encode() if data else None)
    except urllib.error.HTTPError as exc:
        response = exc
    with response:
        return response.status, response.read().decode()
def token(html):
    return re.search(r'name="csrf" value="([a-f0-9]+)"', html)[1]
original = Path('data/articles.json').read_bytes()
try:
    status, html = request('admin.php')
    assert status == 200 and 'Tên đăng nhập' in html
    assert request('admin.php', {'csrf':'wrong','action':'save'})[0] == 403
    status, html = request('admin.php', {'csrf':token(html), 'action':'login','username':'ci-admin','password':'ci-test-password-123'})
    assert status == 200 and 'Tạo bài mới' in html
    data = dict(csrf=token(html), action='save', original='', slug='test-draft', title='<script>draft</script>', summary='Test', author='CI', date='2026-10-13', minutes='2', category='cong-nghe', body='Test body', status='draft')
    status, html = request('admin.php', data)
    assert status == 200 and '&lt;script&gt;draft&lt;/script&gt;' in html
    assert request('index.php?article=test-draft')[0] == 404
    data.update(csrf=token(html), original='test-draft', status='published')
    request('admin.php', data)
    status, html = request('index.php?article=test-draft')
    assert status == 200 and '&lt;script&gt;draft&lt;/script&gt;' in html and '<script>draft</script>' not in html
    status, html = request('admin.php')
    request('admin.php', {'csrf':token(html), 'action':'logout'})
    assert 'Tên đăng nhập' in request('admin.php')[1]
finally:
    Path('data/articles.json').write_bytes(original)
print('Admin checks passed.')
