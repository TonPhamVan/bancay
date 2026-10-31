"""B1 checks; serial destructive suite uses the repository's bancay_test guard."""
import datetime
import hashlib
import json
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/admin-improvements/b1/evidence'
PRIVATE = ROOT / 'legacy-private/admin-improvements/b1'


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    PRIVATE.mkdir(parents=True, exist_ok=True, mode=0o700)
    prefix = ['docker', 'compose', 'run', '--rm', '--no-deps']
    checks = {
        'php': prefix + ['app', 'php', 'artisan', 'test'],
        'pint': prefix + ['app', 'vendor/bin/pint', '--test'],
        'composer-validate': prefix + ['app', 'composer', 'validate', '--strict'],
        'platform': prefix + ['app', 'composer', 'check-platform-reqs'],
        'exment': prefix + ['app', 'php', 'artisan', 'exment:checkrequire'],
        'composer-audit': prefix + ['app', 'composer', 'audit', '--format=json'],
        'assets': prefix + ['assets', 'sh', '-c', 'npm test && npm run build && npm audit --json'],
    }
    results = {}
    for name, command in checks.items():
        process = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
        log = process.stdout + process.stderr
        path = PRIVATE / (name + '.log')
        path.write_text(log)
        path.chmod(0o600)
        result = {'exit_code': process.returncode, 'status': 'PASS' if process.returncode == 0 else 'FAIL',
                  'log_sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
        if name == 'php':
            result['summary'] = re.findall(r'Tests:.*|Duration:.*', log)
        if name == 'assets':
            result['summary'] = re.findall(r'[ℹ#] (?:tests|pass|fail|skipped).*|.*built in.*', log)
            audit = json.loads(process.stdout[process.stdout.index('{\n  "auditReportVersion"'):])
            result['vulnerabilities'] = audit['metadata']['vulnerabilities']
        if name == 'composer-audit':
            audit = json.loads(process.stdout[process.stdout.index('{'):])
            result['advisories'] = len(audit.get('advisories', []))
            result['abandoned'] = len(audit.get('abandoned', []))
        results[name] = result
        print(name, result, flush=True)
        (OUT / 'checks.json').write_text(json.dumps({'at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
            'git': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
            'uncommitted_implementation': True, 'checks': results}, indent=2) + '\n')
    if any(result['status'] != 'PASS' for result in results.values()):
        raise SystemExit(1)


if __name__ == '__main__':
    main()
