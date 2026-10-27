"""Check repository/evidence boundaries after running the documented Phase 1 checks.

Uses metadata and local files only; no import, production connection or schema write.
"""

import hashlib
import json
from pathlib import Path
import re
import subprocess


ROOT = Path(__file__).resolve().parent.parent
EVIDENCE = ROOT / 'docs/phase-1/evidence'


def main():
    (EVIDENCE / 'verification.json').write_text('{"status":"IN_PROGRESS"}\n')
    checks = []

    def check(value, label):
        if not value:
            raise AssertionError(label)
        checks.append(label)

    def read(name):
        return json.loads((EVIDENCE / name).read_text())

    tests = read('tests.json')
    check(tests['tests'] == 41 and tests['failures'] == tests['errors'] == tests['skipped'] == 0,
          '41 automated tests pass on dedicated MariaDB')
    check(any('rollback_and_remigrate' in n for n in tests['test_cases']), 'Full migration rollback/remigrate covered')
    schema = read('schema.json')
    check(len(schema['tables']) == 17 and schema['database'] == 'bancay', '17 native domain tables in new local database')
    columns = lambda table: [{k.lower(): v for k, v in c.items()} for c in schema['tables'][table]['columns']]
    check('price' not in {c['column_name'] for c in columns('products')}, 'Product does not duplicate variant price')
    actor = next(c for c in columns('order_status_histories') if c['column_name'] == 'changed_by')
    check(actor['column_type'].startswith('int(') and 'unsigned' in actor['column_type'], 'History actor matches unsigned INT Exment LoginUser')
    check(all(p['status'] == 'success' for p in read('platform.json')), 'Composer platform requirements pass')
    check(read('composer-validation.json')['status'] == 'PASS', 'Composer manifest/lock strict validation passes')
    requirements = (EVIDENCE / 'exment-requirements.txt').read_text()
    check(requirements.count(' OK') == 7 and ' NG' not in requirements and 'WARNING' not in requirements,
          'All seven Exment runtime requirement checks are OK')
    http = read('http-smoke.json')
    check(http['health']['status'] == 200 and http['login']['final_path'] == '/admin', 'Live local health and native Exment login/dashboard succeed')
    check(http['missing_csrf']['status'] == 419 and http['session_cookie']['http_only'], 'Local CSRF enforcement and session HttpOnly verified')
    check(not (ROOT / 'app/Models/User.php').exists(), 'No duplicate application User/auth model')
    for file in (ROOT / 'app').rglob('*.php'):
        text = file.read_text()
        check(not re.search(r'\bmd5\s*\(|\$_(?:POST|GET)\b', text), f'No MD5/raw HTTP globals: {file.relative_to(ROOT)}')
    composer = json.loads((ROOT / 'composer.json').read_text())
    check('audit' not in composer['config'] and 'policy' not in composer['config'], 'No persistent advisory ignore/block-disable in Composer config')
    lock = json.loads((ROOT / 'composer.lock').read_text())
    versions = {p['name']: p['version'] for p in lock['packages']}
    check(versions['exceedone/exment'] == 'v6.2.15' and versions['laravel/framework'] == 'v10.50.4', 'Requested temporary v6/Laravel10 stack locked')
    for relative in ['.env', 'legacy-private/phase1/admin.env', 'backups/phase-1', 'vendor/autoload.php']:
        result = subprocess.run(['git', 'check-ignore', '-q', relative], cwd=ROOT)
        check(result.returncode == 0, f'Ignored private/generated path: {relative}')
    secrets = []
    for relative in ['.env', 'legacy-private/phase1/admin.env']:
        file = ROOT / relative
        check(file.stat().st_mode & 0o777 == 0o600, f'Private file mode: {relative}')
        for line in file.read_text().splitlines():
            key, _, value = line.partition('=')
            if key in ['APP_KEY', 'DB_PASSWORD', 'DB_ROOT_PASSWORD', 'BANCAY_BOOTSTRAP_PASSWORD'] and value:
                secrets.append(value)
    # Git's own candidate list excludes vendor, raw backups, runtime logs and credentials.
    candidates = subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], cwd=ROOT).decode().split('\0')
    for relative in candidates:
        path = ROOT / relative
        if not relative or not path.is_file():
            continue
        try:
            text = path.read_text(encoding='utf-8-sig')
        except UnicodeDecodeError:
            continue
        check(not any(secret in text for secret in secrets), f'No generated credential values in candidate: {relative}')
    documents = [ROOT / 'README.md', ROOT / 'AGENTS.md', *sorted((ROOT / 'docs').rglob('*.md'))]
    links = 0
    for path in documents:
        text = path.read_text(encoding='utf-8-sig')
        check('\ufffd' not in text, f'UTF-8 document: {path.relative_to(ROOT)}')
        for match in re.finditer(r'\[[^\]]+\]\(([^)]+)\)', text):
            target = match.group(1).split('#', 1)[0]
            if target and not re.match(r'https?://', target):
                check((path.parent / target).exists(), f'Local link: {path.relative_to(ROOT)} -> {target}')
                links += 1
    expected_master = 'cc7bd8e17698ac25f04ee531c346c89378ec053bd934621b57e764c3d20f4a85'
    check(hashlib.sha256((ROOT / 'docs/master-plan.md').read_bytes()).hexdigest() == expected_master, 'Master Plan unchanged')
    audit = read('composer-audit.json')
    count = sum(len(values) for values in audit['advisories'].values())
    check(count == 6, 'Six dependency advisory entries retained without hiding failures')
    result = {
        'status': 'PASS_WITH_KNOWN_DEPENDENCY_RISKS', 'checks_passed': len(checks),
        'automated_tests': tests['tests'], 'assertions': tests['assertions'], 'local_links_checked': links,
        'dependency_audit': {'status': 'FAIL', 'advisory_entries': count, 'packages': list(audit['advisories'])},
        'production_deployment': 'BLOCKED', 'phase_2': 'READY WITH RISKS (local only)', 'checks': checks,
    }
    (EVIDENCE / 'verification.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps({k: v for k, v in result.items() if k != 'checks'}, indent=2))


if __name__ == '__main__':
    main()
