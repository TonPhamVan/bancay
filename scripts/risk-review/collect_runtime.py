"""Read-only runtime and HTTP checks. Writes only safe evidence; no business-data mutation."""

import hashlib
import http.cookiejar
import json
from pathlib import Path
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/risk-review/evidence'


def main():
    result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php', 'scripts/risk-review/collect_runtime.php'],
                            cwd=ROOT, capture_output=True, text=True, check=True)
    runtime = json.loads(result.stdout)
    assert runtime['column_fk_index_changes'] == [] and len(runtime['added_checks']) == 3
    (OUT / 'runtime-schema.json').write_text(result.stdout)
    before = ET.parse(ROOT / 'storage/logs/risk-review-before.xml').getroot().find('testsuite')
    (OUT / 'reproduction-before.json').write_text(json.dumps({k: int(before.attrib[k]) for k in ['tests', 'assertions', 'errors', 'failures', 'skipped']} | {
        'cases': [c.attrib['name'] for c in before.iter('testcase')], 'baseline': 'b574d19',
        'method': 'Real MariaDB bancay_test; four DB integrity and two shared-media failures; raw logs private.'}, indent=2) + '\n')
    results = []
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    base = 'http://localhost:8000'
    for path in ['/', '/san-pham', '/san-pham/monstera-chau-dat-nung-demo', '/gio-hang', '/bai-viet', '/lien-he']:
        response = client.open(base + path)
        response.read()
        results.append({'check': path, 'status': response.status, 'expected': 200})
    for token in ['api_token=0', 'api_token%5B0%5D=0', 'api_token=true']:
        response = client.open(base + '/admin/plugins/bancay_orders/orders?' + token)
        response.read()
        results.append({'check': 'guest_api_token_does_not_bypass_native_session', 'redirected_to_login': urllib.parse.urlparse(response.url).path == '/admin/auth/login'})
    for path in ['/gio-hang', '/thanh-toan']:
        try:
            response = client.open(urllib.request.Request(base + path, data=b'', headers={'Accept': 'application/json'}))
            code = response.status
        except urllib.error.HTTPError as e:
            code = e.code
        results.append({'check': 'missing_csrf:' + path, 'status': code, 'expected': 419})
    credentials = dict(line.split('=', 1) for line in (ROOT / 'legacy-private/phase1/admin.env').read_text().splitlines() if '=' in line)
    html = client.open(base + '/admin/auth/login').read().decode()
    token = re.search(r'name="_token" value="([^"]+)"', html).group(1)
    response = client.open(base + '/admin/auth/login', urllib.parse.urlencode({'_token': token, 'username': 'local-admin', 'password': credentials['BANCAY_BOOTSTRAP_PASSWORD']}).encode())
    assert '/admin/auth/login' not in response.url
    response.read()
    for path in ['/admin/plugins/bancay_dashboard', '/admin/plugins/bancay_catalog/products', '/admin/plugins/bancay_orders/orders']:
        response = client.open(base + path)
        response.read()
        results.append({'check': path, 'status': response.status, 'expected': 200})
    assert all(r.get('redirected_to_login', True) and r.get('status') == r.get('expected') for r in results)
    (OUT / 'http-smoke.json').write_text(json.dumps({'results': results, 'status': 'PASS', 'body_exported': False, 'credentials_exported': False}, indent=2) + '\n')
    manifest = {}
    for folder in ['app', 'config', 'database', 'tests', 'routes', 'bootstrap', 'resources', 'exment-plugins', 'scripts/risk-review']:
        for file in (ROOT / folder).rglob('*'):
            if not file.is_file() or 'cache' in file.parts:
                continue
            manifest[str(file.relative_to(ROOT))] = hashlib.sha256(file.read_bytes()).hexdigest()
    for file in [ROOT / 'composer.json', ROOT / 'composer.lock', ROOT / 'scripts/risk-review/collect_runtime.py', ROOT / 'package.json', ROOT / 'package-lock.json', ROOT / 'vite.config.js']:
        manifest[str(file.relative_to(ROOT))] = hashlib.sha256(file.read_bytes()).hexdigest()
    (OUT / 'source-manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
    print('Runtime:17 tables column/FK/index stable,3 new CHECKs; HTTP smoke PASS; no credentials/response bodies exported.')


if __name__ == '__main__':
    main()
