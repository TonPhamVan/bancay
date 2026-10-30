"""Verify current remediation evidence and ownership guards without operational writes."""
import datetime
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/business-ui-remediation/20261030-r1/evidence'
PRIVATE = ROOT / 'legacy-private/business-remediation/20261030-r1'
sys.path.insert(0, str(ROOT / 'scripts/readiness'))
from verify_backup import query


def read(name):
    return json.loads((OUT / name).read_text())


tests = read('tests.json')
assert tests['status'] == 'PASS' and tests['total'] == 180 and tests['assertions'] == 1187
assert tests['failures'] == tests['errors'] == tests['skipped'] == 0
assert len(tests['cases']) == 180 and all(case['status'] == 'PASS' for case in tests['cases'])
for name in ['data-integrity.json', 'http-seo.json', 'e2e-database.json', 'performance.json']:
    assert read(name)['status'] == 'PASS', name
assert not read('composer-audit.json')['advisories'] and not read('composer-audit.json')['abandoned']
assert read('npm-audit.json')['metadata']['vulnerabilities']['total'] == 0
quality = read('quality.json')
assert quality['build']['status'] == 'PASS' and quality['js_tests']['passed'] == 5
assert all(len(row['logs']) == 0 for row in read('browser-logs.json'))
for row in read('responsive.json')['pages'] + read('commerce-responsive.json'):
    assert row['width'] >= row['scroll'] and row['brokenVisible'] == 0
assert len(read('responsive.json')['pages']) + len(read('commerce-responsive.json')) == 24
assert read('responsive.json')['adminNarrow']['scrollLeft'] > 0
assert read('content-queue.json')['decisions_applied'] == 0
receipt = read('qa-environment.json')
assert hashlib.sha256((PRIVATE / 'source-copy.sql').read_bytes()).hexdigest() == receipt['dump_sha256']
cleanup = read('cleanup.json')
assert all(cleanup['all_base_tables_equal'].values())
assert cleanup['qa_database_removed'] and cleanup['qa_container_removed']
qa_user = 'bancay_biz_' + hashlib.sha256(b'20261030-r1').hexdigest()[:8]
assert not query(f"SELECT User FROM mysql.user WHERE User='{qa_user}' AND Host='%';")
assert not query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='bancay_release_candidate_20261030_001';")
# Add independently checked account cleanup; no credential or operational data is emitted.
cleanup['qa_account_removed'] = True
cleanup['native_log_note'] = 'No main native audit-log differences observed.'
(OUT / 'cleanup.json').write_text(json.dumps(cleanup, indent=2) + '\n')

env = os.environ.copy()
env.update(BANCAY_BUSINESS_RUN='20261030-r1', BANCAY_BUSINESS_STAMP='20261030_999')
guard = subprocess.run(['python3', 'scripts/business-review/qa_environment.py', 'stop'], cwd=ROOT,
                       env=env, capture_output=True, text=True)
assert guard.returncode != 0 and 'QA ownership receipt mismatch' in guard.stderr
subprocess.run(['git', 'diff', '--check'], cwd=ROOT, check=True)
files = [p for p in OUT.parent.rglob('*') if p.is_file() and p.name != 'artifact-verification.json']
files += [ROOT / 'docs/business-ui-remediation-report.md', ROOT / 'docs/business-ui-remediation/content-inputs.md']
source_paths = subprocess.run(['git', 'diff', '--name-only'], cwd=ROOT, check=True, capture_output=True, text=True).stdout.splitlines()
source_paths += subprocess.run(['git', 'ls-files', '--others', '--exclude-standard'], cwd=ROOT, check=True, capture_output=True, text=True).stdout.splitlines()
source_paths = sorted(set(p for p in source_paths if p.startswith(('app/', 'resources/', 'routes/', 'config/', 'public/', 'tests/', 'scripts/'))))
result = {'at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'status': 'PASS',
          'git_baseline': subprocess.run(['git', 'rev-parse', 'HEAD'], cwd=ROOT, check=True, capture_output=True, text=True).stdout.strip(),
          'uncommitted': True, 'qa_ownership_mismatch_rejected': True, 'qa_account_removed': True,
          'source_files_sha256': {p: hashlib.sha256((ROOT / p).read_bytes()).hexdigest() for p in source_paths},
          'artifacts': {str(p.relative_to(ROOT)): {'bytes': p.stat().st_size, 'sha256': hashlib.sha256(p.read_bytes()).hexdigest()} for p in sorted(files)}}
(OUT / 'artifact-verification.json').write_text(json.dumps(result, indent=2) + '\n')
print('PASS:', len(files), 'artifacts;', len(source_paths), 'source hashes; test/audit/HTTP/DB/E2E/cleanup and ownership guard verified.')
