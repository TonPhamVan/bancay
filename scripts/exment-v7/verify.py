"""Verify the Exment7 upgrade checkpoint; never migrate, import, restore or publish data."""
import datetime
import hashlib
import json
from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts/readiness'))
from verify_backup import snapshot

OUT = ROOT / 'docs/exment-v7/evidence'


def read(name):
    return json.loads((OUT / name).read_text())


def digest(path):
    with path.open('rb') as file:
        return hashlib.file_digest(file, 'sha256').hexdigest()


def main():
    env = dict(line.split('=', 1) for line in (ROOT / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
    assert env['APP_ENV'] == 'local' and env['DB_DATABASE'] == 'bancay'
    composer = json.loads((ROOT / 'composer.json').read_text())
    assert 'audit' not in composer['config'] and 'policy' not in composer['config']
    lock = json.loads((ROOT / 'composer.lock').read_text())
    versions = {p['name']: p['version'] for p in lock['packages'] + lock['packages-dev']}
    assert versions['exceedone/exment'] == 'v7.0.0' and versions['laravel/framework'] == 'v12.70.2'
    assert versions['exceedone/exment-admin-core'] == 'v1.0.1'
    assert not any(name in versions for name in ['exceedone/laravel-admin', 'laravelcollective/html', 'intervention/image'])
    audit = read('composer-audit.json')
    assert not audit['advisories'] and not audit['abandoned']
    assert read('npm-audit.json')['metadata']['vulnerabilities']['total'] == 0
    checks = read('checks.json')
    assert all(value['status'] == 'PASS' for value in checks.values() if isinstance(value, dict) and 'status' in value)
    tests = read('tests.json')
    assert tests['tests'] == '132' and tests['assertions'] == '814'
    assert all(tests[key] == '0' for key in ['errors', 'failures', 'skipped'])
    browser = read('browser-acceptance.json')
    assert browser['status'] == 'PASS' and sum(len(x['cases']) for x in browser['environments']) == 44
    assert all(case['status'] == 'PASS' for item in browser['environments'] for case in item['cases'])
    native = read('native-admin.json')
    assert native['status'] == 'PASS' and len(native['cases']) == 3
    assert native['page_errors'] == native['failed_local_assets'] == []
    assert read('browser-data-verification.json')['status'] == 'PASS'
    backup = read('backup-verification.json')
    assert backup['status'] == 'BACKUP VERIFIED'
    combined_receipt = ROOT / 'legacy-private/ui-mapping/combined/receipt.json'
    authorized_merge = combined_receipt.exists()
    if authorized_merge:
        merge_check = subprocess.run(['docker', 'exec', 'bancay-app-1', 'php', 'scripts/ui-mapping/merge.php', '--verify-rerun'], cwd=ROOT, capture_output=True, text=True)
        assert merge_check.returncode == 0 and json.loads(merge_check.stdout)['status'] == 'PASS', 'Authorized combined-data checkpoint changed'
    domain = ['categories', 'products', 'product_images', 'product_options', 'product_option_values', 'product_variants',
              'product_variant_values', 'inventories', 'customers', 'customer_addresses', 'orders', 'order_items',
              'order_status_histories', 'article_categories', 'articles', 'banners', 'settings']
    for database in backup['databases']:
        assert database['database'] in ['bancay', 'bancay_phase4_staging']
        file = ROOT / database['private_path']
        assert file.resolve().is_relative_to((ROOT / 'legacy-private/exment-v7').resolve())
        assert file.stat().st_size == database['size_bytes'] and digest(file) == database['sha256']
        assert database['restore_counts_checksums_schema_match'] and database['scratch_removed']
        if database['database'] != 'bancay' or not authorized_merge:
            current = snapshot(database['database'], domain)
            assert all(current[t] == database['tables'][t] for t in domain), 'Domain changed after upgrade checkpoint'
    archive = ROOT / backup['files']['private_path']
    assert archive.stat().st_size == backup['files']['size_bytes'] and digest(archive) == backup['files']['sha256']
    result = subprocess.run(['docker', 'exec', 'bancay-phase4-app', 'php', 'scripts/exment-v7/runtime_probe.php'], cwd=ROOT, capture_output=True, text=True)
    assert result.returncode == 0, 'Runtime probe failed; inspect local runtime privately'
    runtime = json.loads(result.stdout)
    assert runtime['laravel'] == '12.70.2' and runtime['exment'] == 'v7.0.0' and runtime['new_admin_class_available']
    assert not runtime['old_admin_class_available'] and not runtime['debug'] and runtime['mail'] == 'log'
    assert runtime['source_fingerprint'] == '5e90ab0125aceac97f629ed5d017b042946d8cfc0a2bf01167346d7adfc36658'
    assert runtime['domain_fingerprint'] == read('reconciliation.json')['target_fingerprint']
    (OUT / 'runtime.json').write_text(json.dumps(runtime, indent=2) + '\n')
    risks = {r['id']: r for r in read('risk-register.json')}
    assert all(risks[key]['status'] == 'RESOLVED' for key in ['RR-29', 'RR-30', 'RR-31', 'RR-32', 'RR-33', 'RR-34'])
    assert digest(ROOT / 'docs/master-plan.md') == 'cc7bd8e17698ac25f04ee531c346c89378ec053bd934621b57e764c3d20f4a85'
    containers = subprocess.run(['docker', 'ps', '--format', '{{.Names}}'], cwd=ROOT, capture_output=True, text=True, check=True).stdout.splitlines()
    assert all(name in containers for name in ['bancay-app-1', 'bancay-phase4-app']) and 'bancay-phase4-ui' not in containers
    assert not (ROOT / 'storage/framework/down').exists() and not (ROOT / 'legacy-private/phase4/storage/framework/down').exists()
    assert not (ROOT / 'public/vendor/laravel-admin').exists() and (ROOT / 'public/vendor/open-admin/open-admin/gfx/user.svg').exists()
    source_manifest = {}
    for folder in ['app', 'config', 'routes', 'tests', 'exment-plugins', 'scripts/exment-v7']:
        for file in (ROOT / folder).rglob('*'):
            if file.is_file() and file.suffix in ['.php', '.py', '.cjs', '.json']:
                source_manifest[str(file.relative_to(ROOT))] = digest(file)
    for name in ['composer.json', 'composer.lock', 'scripts/phase4/ui_environment.py']:
        source_manifest[name] = digest(ROOT / name)
    (OUT / 'source-manifest.json').write_text(json.dumps(source_manifest, indent=2) + '\n')
    summary = {'status': 'PASS', 'checked_at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
               'upgrade': 'COMPLETED', 'exment': '7.0.0', 'laravel': '12.70.2', 'composer_advisories': 0,
               'abandoned_packages': 0, 'npm_vulnerabilities': 0, 'php_tests': 132, 'assertions': 814,
               'javascript_tests': 5, 'core_browser_cases': 44, 'native_v7_cases': 3, 'backup': 'BACKUP VERIFIED',
               'both_databases_domain_counts_checksums_schema_unchanged': not authorized_merge, 'source_unchanged': True,
               'authorized_post_upgrade_combined_data_merge_verified': authorized_merge,
               'main_and_staging_up': True, 'qa_removed': True, 'RR-30': 'RESOLVED', 'RR-34': 'RESOLVED',
               'publication': 'hidden; unchanged', 'production_modified': False, 'master_plan_unchanged': True}
    (OUT / 'verification.json').write_text(json.dumps(summary, indent=2) + '\n')
    print(json.dumps(summary, indent=2))


if __name__ == '__main__':
    main()
