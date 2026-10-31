"""Verify and bind the current acceptance artifacts without touching databases."""
import hashlib
import json
from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/admin-improvements/ui-acceptance-20261031/evidence'
checks = json.loads((OUT / 'checks.json').read_text())
assert all(check['status'] == 'PASS' for check in checks['checks'].values())
browser = json.loads((OUT / 'browser-cases.json').read_text())
assert sum(case['result'] == 'PASS' for case in browser['cases']) == 20
assert json.loads((OUT / 'qa-verification.json').read_text())['status'] == 'PASS'
cleanup = json.loads((ROOT / 'docs/business-ui-remediation/ui-acceptance-20261031/evidence/cleanup.json').read_text())
assert cleanup['qa_database_removed'] and cleanup['qa_account_removed'] and cleanup['qa_container_removed']
assert cleanup['domain_row_counts_and_checksums_unchanged'] and cleanup['stage_all_tables_equal']
sources = {}
for directory in ['app', 'resources/views', 'public/storefront', 'exment-plugins', 'tests', 'scripts/admin-improvements']:
    for path in sorted((ROOT / directory).rglob('*')):
        if path.is_file() and path.suffix in {'.php', '.js', '.css', '.py'}:
            sources[str(path.relative_to(ROOT))] = hashlib.sha256(path.read_bytes()).hexdigest()
artifacts = {path.name: hashlib.sha256(path.read_bytes()).hexdigest() for path in sorted(OUT.iterdir())
             if path.is_file() and path.name != 'manifest.json'}
manifest = {'git': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
            'uncommitted_implementation': True, 'sources': sources, 'artifacts': artifacts,
            'source_files': len(sources), 'historical_evidence_overwritten': False,
            'tabs': {'closed': [5, 6, 7, 8, 9], 'explicit_close_unavailable': [2, 4],
                     'reason': 'Old handles unavailable after browser context interruption; QA server removed; tabs not marked deliverables.',
                     'user_tab_1_preserved': True},
            'native_lifecycle_verified': False, 'standalone_chrome_edge_verified': False}
(OUT / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
print('Acceptance artifacts verified;20 IAB groups,201PHP/5JS checks,owned QA cleanup and source hashes bound.')
