"""HTTP behavior checks against an already running PHP server."""
import urllib.request
import urllib.error
import urllib.parse

BASE = 'http://127.0.0.1:8080/'

def get(params=None):
    request = urllib.request.Request(BASE + ('?' + urllib.parse.urlencode(params) if params else ''))
    try:
        response = urllib.request.urlopen(request, timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read().decode('utf-8'), response.headers

status, html, headers = get()
assert status == 200 and 'nhịp' in html
assert 'Content-Security-Policy' in headers
assert html.count('class="card"') == 6
status, html, _ = get({'page': 2})
assert status == 200 and html.count('class="card"') == 3
status, html, _ = get({'category': 'cong-nghe'})
assert status == 200 and html.count('class="card"') == 3
status, html, _ = get({'article': 'ai-va-cuoc-song'})
assert status == 200 and 'class="prose"' in html
status, html, _ = get({'q': 'TÀI KHOẢN'})
assert status == 200 and html.count('class="card"') == 1
status, html, _ = get({'q': '<script>alert(1)</script>'})
assert status == 200 and '<script>alert(1)</script>' not in html and '&lt;script&gt;' in html
status, html, _ = get({'q[]': 'x'})
assert status == 200
for params in [{'article': '../README.md'}, {'category': 'missing'}]:
    status, html, _ = get(params)
    assert status == 404 and 'Không tìm thấy' in html
print('All HTTP smoke checks passed.')
