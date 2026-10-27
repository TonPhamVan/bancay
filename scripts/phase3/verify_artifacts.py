"""Verify Phase3 source/artifact integrity, local links and basic secret hygiene; no DB writes."""

import csv
import hashlib
import json
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/phase-3/evidence'
checks = []


def check(name, condition):
    checks.append({'check': name, 'result': 'PASS' if condition else 'FAIL'})


def read(name):
    return json.loads((OUT / name).read_text())


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main():
    for path, digest in read('source-manifest.json').items():
        file = ROOT / path
        check('source:' + path, file.is_file() and sha(file) == digest)
    check('master_plan_original_bytes', sha(ROOT / 'docs/master-plan.md') == 'cc7bd8e17698ac25f04ee531c346c89378ec053bd934621b57e764c3d20f4a85')
    attachment = Path('/mnt/c/Users/Admin/.codex/attachments/0a8dfa36-4b0b-49d7-be1a-b22948f1aeeb/Pasted text.txt')
    if attachment.is_file():
        check('phase3_request_original_bytes', sha(attachment) == sha(ROOT / 'docs/phase-3/request.md'))
    summary = read('tests.json')
    check('96_php_tests_no_fail_skip', summary['tests'] >= 96 and all(summary[k] == 0 for k in ['errors', 'failures', 'skipped']))
    check('node_tests', read('js-tests.json')['tests'] == 5 and read('js-tests.json')['exit_code'] == 0)
    check('domain_schema_unchanged', read('schema-comparison.json')['structural_changes'] == [])
    check('one_technical_table', read('schema-comparison.json')['added_technical_table'] == 'checkout_attempts')
    check('npm_audit_zero', read('npm-audit.json')['metadata']['vulnerabilities']['total'] == 0)
    audit = read('composer-audit.json')
    check('known_composer_findings_recorded', sum(len(v) for v in audit['advisories'].values()) == 6)
    check('production_still_blocked', read('verification.json')['production'].startswith('BLOCKED'))
    for record in read('verification.json')['checks']:
        check('execution:' + record['name'], record['exit_code'] == record['expected_exit_code'])
    for record in read('http-smoke.json')['checks']:
        check('http:' + record['check'], record['status'] == record['expected'])
    ui = read('ui-tests.json')
    check('browser_console_clean', ui['console']['error_warning_count'] == 0)
    check('desktop_core_ui', any(r['flow'] == 'desktop-cart-checkout-success' and r['result'] == 'PASS' for r in ui['flows']))
    check('mobile_core_ui', any(r['flow'] == 'mobile-full-core-flow' and r['result'] == 'PASS' for r in ui['flows']))
    for record in ui['flows']:
        paths = record.get('evidence', [])
        for path in paths if isinstance(paths, list) else [paths]:
            file = OUT / path
            check('ui_evidence:' + path, file.is_file() and file.stat().st_size > 1000)
    rows = list(csv.reader((ROOT / 'docs/phase-3/legacy-comparison.csv').open()))
    check('legacy_comparison_22_rows_5_fields', len(rows) == 23 and all(len(r) == 5 for r in rows))
    headings = re.findall(r'^## (\d+)\.', (ROOT / 'docs/phase-3-report.md').read_text(), re.M)
    check('report_26_required_sections', headings == [str(i) for i in range(1, 27)])
    docs = [ROOT / p for p in ['README.md', 'AGENTS.md', 'docs/frontend-architecture.md', 'docs/frontend-routes.md',
        'docs/cart-checkout.md', 'docs/seo-frontend.md', 'docs/phase-3-report.md',
        'docs/phase-3/architecture-change-proposal.md', 'docs/phase-3/image-provenance.md']]
    link_count = 0
    for file in docs:
        for target in re.findall(r'\]\(([^)]+)\)', file.read_text()):
            target = target.strip('<>').split('#')[0]
            if not target or re.match(r'^[a-z]+:', target, re.I):
                continue
            link_count += 1
            check('doc_link:' + str(file.relative_to(ROOT)) + '→' + target, (file.parent / target).exists())
    tracked = subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], cwd=ROOT).decode().split('\0')
    check('private_inputs_not_versioned', not any(p.startswith(('legacy-private/', 'backups/')) or p == '.env' for p in tracked if p))
    changed = set(subprocess.check_output(['git', 'diff', '--name-only', 'HEAD'], cwd=ROOT).decode().splitlines())
    untracked = set(subprocess.check_output(['git', 'ls-files', '--others', '--exclude-standard'], cwd=ROOT).decode().splitlines())
    secret = re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|gh[pousr]_[A-Za-z0-9]{30,}|AKIA[0-9A-Z]{16}|sk_live_[A-Za-z0-9]{20,}')
    for path in sorted(changed | untracked):
        file = ROOT / path
        if not file.is_file():
            continue
        try:
            body = file.read_text()
        except UnicodeError:
            continue
        # The checker source contains the regex itself, not a credential.
        check('secret_hygiene:' + path, not secret.search(body))
    result = {'date': '2026-10-28', 'checks_total': len(checks), 'checks_passed': sum(c['result'] == 'PASS' for c in checks),
              'local_document_links': link_count, 'checks': checks,
              'limits': 'Static integrity/basic token patterns and selected synthetic evidence; not a penetration test or exhaustive PII scanner.'}
    (OUT / 'artifact-verification.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
    (OUT / 'artifact-manifest.json').write_text(json.dumps({str(p.relative_to(OUT)): sha(p) for p in sorted(OUT.iterdir()) if p.is_file() and p.name != 'artifact-manifest.json'}, indent=2) + '\n')
    failed = [c['check'] for c in checks if c['result'] != 'PASS']
    print(json.dumps({'checks': len(checks), 'passed': len(checks) - len(failed), 'failed': failed, 'document_links': link_count}, ensure_ascii=False))
    raise SystemExit(1 if failed else 0)


if __name__ == '__main__':
    main()
