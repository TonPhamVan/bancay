"""Prepare private copies of existing collectors with a new evidence destination.

This preserves historical reports and avoids duplicating versioned browser suites.
Both original and prepared hashes are recorded; no production command is executed.
"""
import hashlib
import json
import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/release-readiness/recheck-54a0a2f'
PRIVATE = ROOT / 'legacy-private/release-recheck'
NAMES = ['checks.py', 'security_review.py', 'seo.py', 'browser_acceptance.cjs',
         'verify_ui.py', 'responsive.cjs', 'admin-e2e.cjs', 'security-http.cjs',
         'checkpoint.py', 'recover_window.py', 'code_checkpoint.py', 'performance.php']

OUT.mkdir(parents=True, exist_ok=True)
PRIVATE.mkdir(parents=True, exist_ok=True)
os.chmod(PRIVATE, 0o700)
manifest = []
for name in NAMES:
    source = ROOT / 'scripts/release-readiness' / name
    content = source.read_text()
    assert 'docs/release-readiness/evidence' in content or name == 'performance.php'
    prepared = content.replace('docs/release-readiness/evidence', 'docs/release-readiness/recheck-54a0a2f')
    if name == 'checks.py':
        prepared = prepared.replace('legacy-private/release-readiness', 'legacy-private/release-recheck')
    if name in ['admin-e2e.cjs', 'responsive.cjs', 'security-http.cjs']:
        prepared = prepared.replace("'legacy-private/release-readiness'", "'legacy-private/release-recheck'")
    target = PRIVATE / name
    target.write_text(prepared)
    os.chmod(target, 0o600)
    manifest.append({'source': str(source.relative_to(ROOT)),
                     'source_sha256': hashlib.sha256(source.read_bytes()).hexdigest(),
                     'prepared_path': str(target.relative_to(ROOT)),
                     'prepared_sha256': hashlib.sha256(target.read_bytes()).hexdigest(),
                     'change': 'evidence/raw-log destination only; same test cases'})
(OUT / 'collector-provenance.json').write_text(json.dumps(manifest, indent=2) + '\n')
print('Prepared', len(manifest), 'collectors; historical evidence untouched')
