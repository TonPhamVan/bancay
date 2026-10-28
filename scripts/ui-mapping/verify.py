"""Verify combined local data/UI checkpoint without importing, publishing or creating orders."""
import datetime
import hashlib
import json
from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/ui-mapping/evidence'


def read(name):
    return json.loads((OUT / name).read_text())


def digest(path):
    with path.open('rb') as file:
        return hashlib.file_digest(file, 'sha256').hexdigest()


def main():
    merged = read('combined-merge.json')
    assert merged['status'] == 'PASS' and merged['source_unchanged'] and merged['original_demo_rows_unchanged']
    assert merged['after_counts']['products'] == 121 and merged['after_counts']['product_variants'] == 142
    assert merged['after_counts']['articles'] == 72 and merged['after_counts']['orders'] == 258
    assert merged['backup']['status'] == 'BACKUP VERIFIED'
    for backup in merged['backup']['databases']:
        file = ROOT / backup['private_path']
        assert file.resolve().is_relative_to((ROOT / 'legacy-private/ui-mapping/combined').resolve())
        assert file.stat().st_size == backup['size_bytes'] and digest(file) == backup['sha256']
        assert backup['restore_counts_data_checksums_schema_match'] and backup['scratch_removed']
    archive = merged['backup']['demo_media']
    assert digest(ROOT / archive['private_path']) == archive['sha256']
    check = subprocess.run(['docker', 'exec', 'bancay-app-1', 'php', 'scripts/ui-mapping/merge.php', '--verify-rerun'], cwd=ROOT, capture_output=True, text=True)
    assert check.returncode == 0
    receipt_check = json.loads(check.stdout)
    assert receipt_check['status'] == 'PASS' and receipt_check['created'] == 0 and receipt_check['domain_fingerprint_unchanged']
    mapping = read('mapping.json')
    assert mapping['status'] == 'PASS' and mapping['issues'] == []
    modes = {row['mode']: row for row in mapping['modes']}
    assert modes['combined']['lists']['products']['records'] == 121 and modes['combined']['checked_variants'] == 142
    assert modes['migrated']['lists']['products']['records'] == 115 and modes['migrated']['checked_variants'] == 120
    frontend = read('combined-frontend.json')
    assert frontend['status'] == 'PASS' and frontend['entities']['products']['rendered_records'] == 120
    assert frontend['entities']['articles']['rendered_records'] == 71 and frontend['local_noindex']
    redirects = read('combined-redirects.json')
    assert redirects['status'] == 'PASS' and redirects['passed'] == redirects['checked_urls'] == 201
    browser = read('browser-acceptance.json')
    assert browser['status'] == 'PASS' and 'combined' in browser['dataset']
    assert sum(len(x['cases']) for x in browser['environments']) == 44
    assert read('browser-data-verification.json')['status'] == 'PASS'
    tests = read('tests.json')
    assert tests['status'] == 'PASS' and tests['tests'] == '137' and tests['assertions'] == '860'
    assert all(tests[key] == '0' for key in ['failures', 'errors', 'skipped'])
    assert not read('composer-audit.json')['advisories'] and not read('composer-audit.json')['abandoned']
    assert all(item['status'] == 'PASS' for item in read('checks.json').values())
    assert digest(ROOT / 'docs/master-plan.md') == 'cc7bd8e17698ac25f04ee531c346c89378ec053bd934621b57e764c3d20f4a85'
    assert not (ROOT / 'storage/framework/down').exists() and not (ROOT / 'legacy-private/phase4/storage/framework/down').exists()
    containers = subprocess.run(['docker', 'ps', '--format', '{{.Names}}'], cwd=ROOT, capture_output=True, text=True, check=True).stdout.splitlines()
    assert 'bancay-phase4-ui' not in containers and all(x in containers for x in ['bancay-app-1', 'bancay-phase4-app'])
    assert subprocess.run(['git', 'diff', '--check'], cwd=ROOT, capture_output=True).returncode == 0
    for script in (ROOT / 'scripts/ui-mapping').glob('*.py'):
        compile(script.read_text(), str(script), 'exec')
    files = {}
    for directory in ['app/Legacy', 'resources/views/admin/store', 'scripts/ui-mapping']:
        for file in (ROOT / directory).rglob('*'):
            if file.is_file() and file.suffix in ['.php', '.py', '.cjs']:
                files[str(file.relative_to(ROOT))] = digest(file)
    for name in ['resources/views/layouts/store.blade.php', 'tests/Feature/AdminFormEncodingTest.php', 'tests/Feature/LocalDatasetMergerTest.php', 'tests/Feature/StorefrontTest.php']:
        files[name] = digest(ROOT / name)
    (OUT / 'source-manifest.json').write_text(json.dumps(files, indent=2) + '\n')
    summary = {'status': 'PASS', 'checked_at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
               'website': 'http://localhost:8000', 'products': 121, 'variants': 142, 'articles': 72, 'orders': 258,
               'public_products': 120, 'public_articles': 71, 'original_demo_preserved': True, 'source_stage_preserved': True,
               'backup': 'BACKUP VERIFIED', 'rerun_created': 0, 'ui_mismatches': 0, 'php_tests': 137, 'assertions': 860,
               'browser_cases': 44, 'old_redirects_passed': 201, 'composer_advisories': 0, 'qa_removed': True,
               'known_frontend_banner_gaps': {'sidebar': 10, 'content_bottom': 3}, 'production_deployed': False}
    (OUT / 'verification.json').write_text(json.dumps(summary, indent=2) + '\n')
    print(json.dumps(summary, indent=2))


if __name__ == '__main__':
    main()
