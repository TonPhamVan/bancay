"""Verify recheck evidence; PASS never grants production release approval."""
import datetime
import hashlib
import json
from pathlib import Path
import py_compile
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/release-readiness/recheck-54a0a2f'
sys.path.insert(0, str(ROOT / 'scripts/readiness'))
from verify_backup import query, snapshot

checks = []


def read(name):
    return json.loads((OUT / name).read_text())


def require(condition, name):
    checks.append({'check': name, 'status': 'PASS' if condition else 'FAIL'})
    if not condition:
        raise AssertionError(name)


for name in ['main', 'stage', 'migration-seo', 'reconciliation', 'performance', 'seo',
             'browser-acceptance', 'browser-data-verification', 'responsive', 'admin-frontend-e2e',
             'security-http', 'rollback-rehearsal', 'offline-delta-export', 'delta-guards',
             'window-restore', 'backup-matrix', 'code-checkpoint', 'production-config-preflight',
             'logging', 'scheduler-execution', 'source-preservation', 'source-security']:
    require(read(name + '.json')['status'] == 'PASS', name)
tests = read('tests.json')
require(tuple(tests[k] for k in ['tests', 'assertions', 'failures', 'errors', 'skipped'])
        == (164, 1062, 0, 0, 0), '164 PHP/1062 assertions, no failures/errors/skips')
delta = [t for t in tests['cases'] if t['class'] == 'Tests\\Feature\\ReleaseOrderDeltaTest']
require(len(delta) == 12 and all(t['status'] == 'PASS' for t in delta), '12 recovery delta tests')
require(read('delta-reproduction.json')['before']['status'] == 'CONFIRMED'
        and read('delta-reproduction.json')['after']['status'] == 'PASS', 'reproduced defect fixed')
for name in ['checks-php.json', 'checks-assets.json']:
    require(all(v['status'] == 'PASS' for v in read(name).values()), name)
require(not read('composer-audit.json')['advisories'] and not read('composer-audit.json')['abandoned'], 'Composer 0')
require(read('npm-audit.json')['metadata']['vulnerabilities']['total'] == 0, 'npm 0')
seo = read('seo.json')
require(len(seo['redirects']) == 201 and len(seo['pages']) == 221, '201 redirects/221 targets')
require(all(r['status'] == 'PASS' and r['status_code'] == 301 and r['target_status'] == 200
            and r['chain_hops'] == 1 for r in seo['redirects']), 'actual direct redirects')
browser = read('browser-acceptance.json')['environments']
require(sum(len(e['cases']) for e in browser) == 47
        and all(c['status'] == 'PASS' for e in browser for c in e['cases']), '47 branded browser cases')
require(all(not e['console_errors'] and not e['page_errors'] and not e['server_errors'] for e in browser), 'browser runtime clean')
require(sum(len(e['cases']) for e in read('responsive.json')['environments']) == 24, '24 responsive cases')
require(len(read('admin-frontend-e2e.json')['cases']) == 13, '13-step admin/public/admin E2E')
require(len(read('security-http.json')['cases']) == 5, '5 actual security HTTP cases')
backup = read('backup-verification.json')
artifact = ROOT / backup['private_relative_path']
require(artifact.stat().st_size == backup['size_bytes']
        and hashlib.sha256(artifact.read_bytes()).hexdigest() == backup['sha256'], 'fresh backup SHA/size')
require(snapshot('bancay', list(backup['tables'])) == backup['tables'], 'all 98 primary base tables unchanged')
window = read('window-restore.json')
require(window['offline_ledger_identical_to_live_qa_export'] and window['derived_views_rebuilt'] == 2
        and window['scratch_and_reader_removed'], '98-table/2-view restore and offline ledger')
code = read('code-checkpoint.json')
manifest = json.loads((ROOT / code['private_manifest']).read_text())
require(all((ROOT / f).is_file() and hashlib.sha256((ROOT / f).read_bytes()).hexdigest() == sha
            for f, sha in manifest.items()), 'sealed code/assets match current source')
archive = (ROOT / code['private_manifest']).parent / code['filename']
require(archive.stat().st_size == code['size_bytes']
        and hashlib.sha256(archive.read_bytes()).hexdigest() == code['sha256'], 'sealed archive SHA/size')
require(hashlib.sha256((ROOT / 'docs/master-plan.md').read_bytes()).hexdigest()
        == 'cc7bd8e17698ac25f04ee531c346c89378ec053bd934621b57e764c3d20f4a85', 'Master Plan original bytes')
historical = ['docs/release-readiness/evidence', 'docs/phase-5/evidence']
for folder in historical:
    names = subprocess.check_output(['git', 'ls-tree', '-r', '--name-only', '54a0a2f', '--', folder], cwd=ROOT, text=True).splitlines()
    require(bool(names), 'historical input exists: ' + folder)
    # Compare through repository clean filters: a pre-existing CRLF CSV has an LF Git blob.
    # Master Plan has -text and is checked byte-for-byte separately above.
    require(all(subprocess.check_output(['git', 'hash-object', '--path=' + name, name], cwd=ROOT).strip()
                == subprocess.check_output(['git', 'rev-parse', '54a0a2f:' + name], cwd=ROOT).strip()
                for name in names), 'historical evidence preserved under Git attributes: ' + folder)
risks = read('risk-register.json')
allowed = {'RESOLVED', 'FALSE POSITIVE', 'ACCEPTED LOW RISK', 'DEFERRED NON-BLOCKING', 'BLOCKER'}
require(len(risks) == 57 and all(r['status'] in allowed for r in risks), 'all 57 risk statuses explicit')
require({r['id'] for r in risks if r['status'] == 'BLOCKER'} == {'RR-39', 'P5-B02', 'P5-B03'}, 'exact 3 blocking risk records')
blockers = read('blockers.json')
require({r['id'] for r in blockers if r['status'] == 'BLOCKER'} == {'P5-B01', 'P5-B02', 'P5-B03'}, '3 external release blockers')
require({r['id'] for r in blockers if r['status'] == 'RESOLVED'} == {'P6-B04', 'P6-B05', 'P6-B06', 'P6-B07'}, '4 confirmed fixes resolved')
require(query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='bancay_phase4_ui' OR SCHEMA_NAME LIKE 'bancay_release_window_%' OR SCHEMA_NAME LIKE 'bancay_release_view_repro_%' OR SCHEMA_NAME LIKE 'bancay_release_verify_legacy_%';") == '0', 'owned QA/restore DBs removed')
require(query("SELECT COUNT(*) FROM mysql.user WHERE User IN ('bancay_p4_ui','bancay_rr_restore');") == '0', 'temporary DB grants revoked')
for file in ['docs/release-readiness/recheck-54a0a2f/report.md', 'docs/release-readiness-report.md',
             'docs/phase-5-report.md', 'docs/security.md', 'docs/testing.md', 'docs/seo.md',
             'docs/production-runbook.md', 'docs/rollback-plan.md']:
    for ref in re.findall(r'\]\(([^)]+)\)', (ROOT / file).read_text()):
        if ref.startswith(('https:', 'http:', '#')):
            continue
        dest = (ROOT / file).parent / ref.split('#')[0]
        require(dest.exists() or dest.resolve() == (OUT / 'verification.json').resolve(), 'doc link: ' + file + ':' + ref)
require(subprocess.run(['git', 'diff', '--check'], cwd=ROOT, capture_output=True).returncode == 0, 'git whitespace')
for file in (ROOT / 'scripts/release-readiness').glob('*.py'):
    py_compile.compile(str(file), doraise=True)
source = {}
for folder in ['app', 'config', 'routes', 'database', 'resources/js', 'resources/css', 'resources/views',
               'tests', 'scripts/release-readiness', 'deployment/directadmin']:
    for file in sorted((ROOT / folder).rglob('*')):
        if file.is_file() and file.suffix in ['.php', '.py', '.js', '.cjs', '.css', '.sh', '.json', '.htaccess'] \
                and '__pycache__' not in str(file) and 'views/vendor' not in str(file):
            source[str(file.relative_to(ROOT))] = hashlib.sha256(file.read_bytes()).hexdigest()
for name in ['composer.lock', 'package-lock.json', '.env.production.example']:
    source[name] = hashlib.sha256((ROOT / name).read_bytes()).hexdigest()
(OUT / 'source-manifest.json').write_text(json.dumps(source, indent=2) + '\n')
result = {'status': 'PASS', 'checked_at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
          'meaning': 'Local evidence consistent; actual production gates remain blocked', 'checks': checks,
          'source_files': len(source), 'phase6_status': 'NOT READY', 'remaining_blockers': ['P5-B01', 'P5-B02', 'P5-B03']}
(OUT / 'verification.json').write_text(json.dumps(result, indent=2) + '\n')
print('Evidence verification PASS:', len(checks), 'checks; PHASE 6 STATUS: NOT READY')
