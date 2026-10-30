"""Read-only public localhost regression; fresh remediation evidence, no historical output."""
import csv
import datetime
import json
from pathlib import Path
import sys
import urllib.parse
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts/phase5'))
from seo import Page, get, pathof

OUT = ROOT / 'docs/business-ui-remediation/20261030-r1/evidence'
result = {'checked_at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
          'base': 'http://localhost:8000', 'redirects': [], 'failures': []}
pages = {}
for row in csv.DictReader((ROOT / 'docs/phase-5/evidence/legacy-url-map.csv').open()):
    code, headers, _, _ = get(row['legacy_url'] + '?utm_source=business-remediation&next=https%3A%2F%2Fevil.test')
    destination = headers.get('Location', headers.get('location', ''))
    target_code, target_headers, body, _ = get(row['new_url'])
    ok = code == 301 and pathof(destination) == row['new_url'] and target_code == 200 and 'Location' not in target_headers
    result['redirects'].append({'entity': row['entity'], 'legacy_id': row['legacy_id'],
                                'old': row['legacy_url'], 'target': row['new_url'],
                                'status_code': code, 'target_status_code': target_code,
                                'status': 'PASS' if ok else 'FAIL'})
    if not ok:
        result['failures'].append({'redirect': row['legacy_url']})
    pages[row['new_url']] = body

code, _, body, _ = get('/sitemap.xml')
locations = [node.text for node in ET.fromstring(body).findall('{*}url/{*}loc')]
private_urls = [url for url in locations if any(part in url for part in ['/admin', '/gio-hang', '/thanh-toan', '/tim-kiem', '/don-hang'])]
result['sitemap'] = {'http_status': code, 'urls': len(locations), 'unique': len(set(locations)),
                     'service_included': 'http://localhost:8000/tu-van-canh-quan' in locations,
                     'private_urls': private_urls}
for url in locations:
    path = pathof(url)
    if path not in pages:
        page_code, _, body, _ = get(path)
        pages[path] = body
        if page_code != 200:
            result['failures'].append({'sitemap': path, 'http_status': page_code})

result['metadata'] = []
assets = set()
for path, body in pages.items():
    page = Page(body.decode())
    ok = page.canonical == result['base'] + path and page.h1 == 1 and bool(page.title.strip()) and bool(page.meta.get('description')) and 'noindex' in page.meta.get('robots', '')
    result['metadata'].append({'path': path, 'status': 'PASS' if ok else 'FAIL'})
    if not ok:
        result['failures'].append({'metadata': path})
    assets.update(pathof(asset) for asset in page.assets if pathof(asset))
result['broken_assets'] = []
for path in sorted(assets):
    asset_code, _, _, _ = get(path)
    if asset_code != 200:
        result['broken_assets'].append({'path': path, 'http_status': asset_code})
result['assets_checked'] = len(assets)
result['robots_isolated'] = 'Disallow: /' in get('/robots.txt')[2].decode()
result['missing_url_404'] = get('/san-pham/no-such-product')[0] == 404
result['status'] = 'PASS' if not result['failures'] and not result['broken_assets'] and not private_urls and result['sitemap']['service_included'] and result['robots_isolated'] and result['missing_url_404'] else 'FAIL'
(OUT / 'http-seo.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
print(result['status'], len(result['redirects']), 'redirects;', len(locations), 'sitemap targets;', len(assets), 'assets;', len(result['failures']), 'failures')
raise SystemExit(0 if result['status'] == 'PASS' else 1)
