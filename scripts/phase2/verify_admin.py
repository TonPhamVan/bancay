"""Verify Phase 2 deliverables, collected evidence and repository privacy boundaries."""

import csv
import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys


ROOT = Path(__file__).resolve().parents[2]
EVIDENCE = ROOT / 'docs/phase-2/evidence'


def main():
    (EVIDENCE / 'verification.json').write_text('{"status":"IN_PROGRESS"}\n')
    checks = []

    def check(condition, label):
        if not condition:
            raise AssertionError(label)
        checks.append(label)

    def read(name):
        return json.loads((EVIDENCE / name).read_text())

    tests = read('tests.json')
    for relative, digest in read('source-manifest.json').items():
        check(hashlib.sha256((ROOT / relative).read_bytes()).hexdigest() == digest, 'Verified source version: ' + relative)
    check(tests['tests'] >= 67 and tests['failures'] == tests['errors'] == tests['skipped'] == 0, 'All Phase 1/2 tests pass, no skipped tests')
    for fragment in ['parallel_stock_order_status', 'empty_database_full_rollback', 'revokes_existing_store_session',
                     'rechecks_permission_and_neutralizes', 'reencodes_pixels', 'single_bound']:
        check(any(fragment in case for case in tests['test_cases']), 'Test evidence: ' + fragment)
    schema = read('schema-comparison.json')
    check(schema['domain_tables_checked'] == 17 and not schema['structural_changes'], '17 domain schemas, FKs and indexes unchanged from Phase 1')
    migration_diff = subprocess.check_output(['git', 'diff', '--name-only', '--', 'database/migrations'], cwd=ROOT)
    check(not migration_diff, 'Phase 1 migrations not edited')
    runtime = read('runtime.json')
    check(runtime['gd_webp'] and runtime['environment'] == 'local', 'Local GD WebP encoder supported')
    check(runtime['plugin_count'] == runtime['typed_store_menu_count'] == 6, 'Six native registered plugins and typed, nonduplicated menu entries')
    group = runtime['admin_middleware']
    check(group.index('admin.auth') < group.index('App\\Http\\Middleware\\StoreAdminGuard') < group.index('admin.permission'), 'Store guard follows native auth and precedes native permission responder')
    ui = read('ui-tests.json')
    check(ui['status'] == 'PASS' and len(ui['flows']) == 20, 'Twenty real browser UI flows documented')
    for path in ui['evidence']:
        check((EVIDENCE / path).stat().st_size > 1000, 'Real UI screenshot: ' + path)
    cleanup = read('ui-account-cleanup.json')
    check(cleanup['system_admin_grants_remaining_for_fixtures'] == cleanup['fixture_role_memberships_remaining'] == 0
          and cleanup['passwords_rotated_without_logging'], 'Temporary UI identities have no remaining privileged grant or known fixture password')
    http = read('http-smoke.json')
    check(http['native_login']['status'] == 200 and http['native_login']['final_path'] == '/admin', 'Original local-admin credential still logs in through native Exment')
    check(all(page['status'] == 200 for page in http['pages']), 'All live local admin grids and health succeed')
    check(http['missing_csrf']['status'] == 419 and http['invalid_category']['status'] == 422, 'Real HTTP CSRF/validation statuses enforced')
    check('is valid' in (EVIDENCE / 'composer-validation.txt').read_text(), 'Composer manifest/lock validate strictly')
    check(all(item['status'] == 'success' for item in read('platform.json')), 'Composer platform requirements pass')
    check((EVIDENCE / 'exment-requirements.txt').read_text().count(' OK') == 7, 'Seven Exment runtime requirement checks pass')
    audit = read('composer-audit.json')
    advisory_count = sum(len(items) for items in audit['advisories'].values())
    check(advisory_count >= 6, 'Upstream dependency audit findings retained and disclosed')
    composer = json.loads((ROOT / 'composer.json').read_text())
    check('audit' not in composer['config'] and 'policy' not in composer['config'], 'No persistent audit ignore or block-disable added')
    check(hashlib.sha256((ROOT / 'docs/master-plan.md').read_bytes()).hexdigest() ==
          'cc7bd8e17698ac25f04ee531c346c89378ec053bd934621b57e764c3d20f4a85', 'Original Master Plan unchanged byte-for-byte')
    with (ROOT / 'docs/phase-2/business-rule-comparison.csv').open() as stream:
        rows = list(csv.DictReader(stream))
    check(len(rows) == 32 and len({row['rule_id'] for row in rows}) == 32, 'All 32 legacy business rules compared')
    check(all(row['classification'] in ['PRESERVED', 'CHANGED', 'DROPPED', 'NOT APPLICABLE'] for row in rows), 'Business-rule classifications explicit')
    check(all('BUSINESS RULE CHANGE' in row['phase_2_behavior_and_reason'] for row in rows if row['classification'] in ['CHANGED', 'DROPPED']), 'Changed/dropped rules disclose behavior, reason and migration/user impact')
    links = 0
    for path in [ROOT / 'README.md', ROOT / 'AGENTS.md', *sorted((ROOT / 'docs').rglob('*.md'))]:
        text = path.read_text(encoding='utf-8-sig')
        check('\ufffd' not in text, 'UTF-8 text: ' + str(path.relative_to(ROOT)))
        for match in re.finditer(r'\[[^\]]+\]\(([^)]+)\)', text):
            target = match.group(1).split('#', 1)[0]
            if target and not re.match(r'https?://', target):
                check((path.parent / target).exists(), 'Document link: ' + target)
                links += 1
    for relative in ['.env', 'legacy-private/phase1/admin.env', 'backups/phase-2', 'storage/app/plugins/BancayCatalog/Plugin.php']:
        check(subprocess.run(['git', 'check-ignore', '-q', relative], cwd=ROOT).returncode == 0, 'Ignored private/runtime path: ' + relative)
    sensitive = set()
    for relative in ['.env', 'legacy-private/phase1/admin.env']:
        for line in (ROOT / relative).read_text().splitlines():
            key, _, value = line.partition('=')
            if key in ['APP_KEY', 'DB_PASSWORD', 'DB_ROOT_PASSWORD', 'BANCAY_BOOTSTRAP_PASSWORD'] and value:
                sensitive.add(value)
    sys.path.insert(0, str(ROOT / 'scripts/phase0'))
    from create_baseline import LITERAL
    original = ROOT / 'legacy-private/phase0/extracted/domains/bancay.vn/public_html'
    for relative in ['application/config/database.php', 'ckfinder/config.php']:
        for match in LITERAL.finditer((original / relative).read_text(encoding='utf-8-sig')):
            if re.search('password|licensekey', match.group('prefix'), re.I) and len(match.group('value')) >= 5:
                sensitive.add(match.group('value'))
    records = json.loads((ROOT / 'legacy-private/phase0/records.private.json').read_text())
    sensitive.update(row['pass'] for row in records['tbladmin'])
    for row in records['tblorder']:
        for key in ['dienthoai', 'email']:
            value = str(row[key]).strip()
            if len(value) >= 8:
                sensitive.add(value)
    files = subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], cwd=ROOT).decode().split('\0')
    for relative in files:
        path = ROOT / relative
        if not relative or not path.is_file():
            continue
        try:
            text = path.read_text(encoding='utf-8-sig')
        except UnicodeDecodeError:
            continue
        check(not any(secret in text for secret in sensitive), 'No known credentials/hashes/legacy order contacts: ' + relative)
    subprocess.run(['git', 'diff', '--check'], cwd=ROOT, check=True)
    result = {'status': 'PASS_WITH_KNOWN_DEPENDENCY_RISKS', 'checks_passed': len(checks), 'tests': tests['tests'], 'assertions': tests['assertions'],
              'local_document_links': links, 'dependency_audit': {'status': 'FAIL', 'advisory_entries': advisory_count},
              'phase_3': 'READY WITH RISKS (local development only)', 'production': 'BLOCKED', 'checks': checks}
    write = EVIDENCE / 'verification.json'
    write.write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps({key: value for key, value in result.items() if key != 'checks'}, indent=2))


if __name__ == '__main__':
    main()
