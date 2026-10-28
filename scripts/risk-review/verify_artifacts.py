"""Verify current review artifacts without rewriting historical phase evidence."""
import hashlib
import json
from pathlib import Path
import re
import subprocess
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/risk-review/evidence'
BASELINE = 'b574d1943aa9081f2449b103ab47dda63f6de74e'


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main():
    results = {}
    def check(name, condition):
        results[name] = bool(condition)
        if not condition:
            raise AssertionError(name)

    check('request_original_bytes', sha(ROOT / 'docs/risk-review/request.md') == '0638b7b6a8bcb2180f380cf3707d946c1dbfe89eaf1f1b0667bb5ada7bd5c7b6')
    check('master_plan_original_bytes', sha(ROOT / 'docs/master-plan.md') == 'cc7bd8e17698ac25f04ee531c346c89378ec053bd934621b57e764c3d20f4a85')
    for name, paths in {
        'historical_evidence_unchanged': ['docs/phase-0', 'docs/phase-1/evidence', 'docs/phase-2/evidence', 'docs/phase-3/evidence'],
        'dependency_vendor_constraints_unchanged': ['composer.json', 'composer.lock'],
        'frontend_assets_unchanged': ['resources', 'package.json', 'package-lock.json', 'vite.config.js'],
        'old_migrations_unchanged': subprocess.run(['git', 'ls-tree', '-r', '--name-only', BASELINE, 'database/migrations'], cwd=ROOT, capture_output=True, text=True, check=True).stdout.splitlines(),
    }.items():
        run = subprocess.run(['git', 'diff', '--exit-code', BASELINE, '--', *paths], cwd=ROOT, capture_output=True)
        check(name, run.returncode == 0)
    tests = json.loads((OUT / 'tests.json').read_text())
    check('composer_audit_failure_preserved', any(r['name'] == 'composer-audit' and r['status'] == 'FAIL' and r['exit_code'] == 1 for r in json.loads((OUT / 'checks.json').read_text())))
    check('full_regression_pass', tests['tests'] == 110 and tests['assertions'] == 656 and all(tests[k] == 0 for k in ['failures', 'errors', 'skipped', 'exit_code']))
    runtime = json.loads((OUT / 'runtime-schema.json').read_text())
    check('schema_17_tables_stable_plus_3_checks', runtime['domain_tables_checked'] == 17 and runtime['column_fk_index_changes'] == [] and len(runtime['added_checks']) == 3 and runtime['migration_applied'])
    check('local_boundary', runtime['debug'] is False and runtime['mail_driver'] == 'log' and runtime['exment_api'] is False and runtime['outside_api'] is False and runtime['php_int_size'] == 8)
    check('real_http_smoke_pass', json.loads((OUT / 'http-smoke.json').read_text())['status'] == 'PASS')
    guard = json.loads((OUT / 'token-guard-source.json').read_text())
    check('guard_source_byte_equal', guard['byte_identical'] and guard['local_sha256'] == guard['official_sha256'] and sha(ROOT / 'vendor/laravel/framework/src/Illuminate/Auth/TokenGuard.php') == guard['local_sha256'])
    risks = json.loads((OUT / 'risk-register.json').read_text())
    check('risk_register_41_complete_unique', len(risks) == 41 and len({r['id'] for r in risks}) == 41)
    check('risk_final_statuses', all(r['status'] in ['RESOLVED', 'FALSE POSITIVE', 'ALREADY RESOLVED', 'ACCEPTED', 'DEFERRED', 'BLOCKED'] for r in risks))
    check('accepted_deferred_have_reason_impact_target', all(r['reason'] and r['impact'] and r['target_phase'] for r in risks if r['status'] in ['ACCEPTED', 'DEFERRED']))
    check('named_blockers_preserved', {r['id'] for r in risks if r['status'] == 'BLOCKED'} == {'RR-28', 'RR-30', 'RR-34'})
    manifest = json.loads((OUT / 'source-manifest.json').read_text())
    check('source_manifest_matches', all(sha(ROOT / name) == value for name, value in manifest.items()))
    report = (ROOT / 'docs/risk-review-report.md').read_text()
    check('report_16_required_sections', re.findall(r'^## (\d+)\.', report, re.M) == [str(i) for i in range(1, 17)])
    docs = [ROOT / name for name in ['README.md', 'AGENTS.md', 'docs/architecture.md', 'docs/database-design.md', 'docs/exment-admin.md', 'docs/frontend-architecture.md', 'docs/exment-mapping.md', 'docs/legacy-mapping.md', 'docs/phase-1-report.md', 'docs/phase-2-report.md', 'docs/phase-3-report.md', 'docs/risk-register.md', 'docs/risk-review-report.md']]
    docs += list((ROOT / 'docs/risk-review').glob('*.md'))
    missing = []
    for doc in docs:
        if doc.name == 'request.md':
            continue
        for target in re.findall(r'\[[^\]]*\]\(([^)]+)\)', doc.read_text()):
            if target.startswith(('https:', 'http:', '#', 'mailto:')):
                continue
            target = target.split('#')[0]
            if (doc.parent / target).resolve() == OUT / 'verification.json':
                continue  # This verifier creates its own output after all checks.
            if not (doc.parent / target).exists():
                missing.append(str(doc.relative_to(ROOT)) + ':' + target)
    check('document_local_links_exist', not missing)
    for name in ['README.md', 'docs/phase-3-report.md', 'docs/risk-register.md', 'docs/risk-review-report.md']:
        check('current_readiness_' + name, 'PHASE 4 STATUS: NOT READY' in (ROOT / name).read_text())
    tracked_changes = subprocess.run(['git', 'diff', '--check'], cwd=ROOT, capture_output=True)
    check('tracked_diff_whitespace', tracked_changes.returncode == 0)
    evidence = list(OUT.glob('*.json'))
    check('no_raw_dump_credentials_response_exports', not any(p.suffix in ['.sql', '.env', '.log', '.xml', '.html'] for p in OUT.iterdir()))
    credentials = dict(line.split('=', 1) for line in (ROOT / 'legacy-private/phase1/admin.env').read_text().splitlines() if '=' in line)
    password = credentials['BANCAY_BOOTSTRAP_PASSWORD']
    check('bootstrap_credential_not_exported', len(password) >= 12 and all(password not in p.read_text() for p in docs + evidence))
    artifacts = {str(p.relative_to(ROOT)): sha(p) for p in docs + evidence if p.name != 'verification.json'}
    output = {'checked_at_utc': datetime.now(timezone.utc).isoformat(), 'baseline': BASELINE, 'status': 'PASS', 'checks': results, 'artifacts': artifacts, 'limits': 'Static artifact verification only. Composer audit FAIL, fresh browser BLOCKED, production restore/load/security NOT TESTED remain unchanged.'}
    (OUT / 'verification.json').write_text(json.dumps(output, indent=2) + '\n')
    print('Artifact verification PASS:', len(results), 'checks;', len(artifacts), 'artifacts; blockers retained.')


if __name__ == '__main__':
    main()
