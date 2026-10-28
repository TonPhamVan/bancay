"""Guarded local URL repair: dry plan, fresh dump/restore verification, apply, reconcile."""
import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts/readiness'))
from verify_backup import db, query, snapshot

TARGET = 'bancay_phase4_staging'
PRIVATE = ROOT / 'legacy-private/phase4/content-risk-closure'
OUT = ROOT / 'docs/phase-4/evidence'


def app(*args):
    result = subprocess.run(['docker', 'exec', 'bancay-phase4-app', 'php', *args], cwd=ROOT, capture_output=True, text=True)
    if result.returncode:
        PRIVATE.mkdir(parents=True, exist_ok=True)
        (PRIVATE / 'failure.log').write_text(result.stdout + result.stderr)
        raise RuntimeError('Guarded operation failed; inspect private failure.log')
    return result.stdout


def main():
    PRIVATE.mkdir(parents=True, exist_ok=True)
    os.chmod(PRIVATE, 0o700)
    env = dict(line.split('=', 1) for line in (ROOT / 'legacy-private/phase4/staging.env').read_text().splitlines() if '=' in line)
    assert env['APP_ENV'] == 'local' and env['DB_DATABASE'] == TARGET and env['DB_HOST'] == 'db'
    app('artisan', 'down')
    try:
        plan = json.loads(app('scripts/phase4/repair_content_urls.php', '--dry-run'))
        now = datetime.datetime.now(datetime.timezone.utc)
        stamp = now.strftime('%Y%m%dT%H%M%SZ')
        dump = PRIVATE / f'{TARGET}-{stamp}.sql'
        tables = query(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='{TARGET}' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;").splitlines()
        assert len(tables) >= 18 and all(re.fullmatch(r'[a-zA-Z0-9_]+', t) for t in tables)
        before = snapshot(TARGET, tables)
        with dump.open('xb') as file:
            os.chmod(dump, 0o600)
            db(['mariadb-dump', '--user=root', '--single-transaction', '--quick', '--hex-blob', '--routines', '--events', '--triggers', '--skip-comments', '--skip-dump-date', TARGET], stdout=file, stderr=subprocess.PIPE)
        content = dump.read_bytes()
        assert len(content) > 10000 and not re.search(rb'(?mi)^(CREATE DATABASE|USE )', content)
        assert set(re.findall(rb'CREATE TABLE `([A-Za-z0-9_]+)`', content)) == {t.encode() for t in tables}
        scratch = 'bancay_content_verify_' + now.strftime('%Y%m%d_%H%M%S')
        assert re.fullmatch(r'bancay_content_verify_[0-9]{8}_[0-9]{6}', scratch)
        assert not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{scratch}';")
        created = False
        try:
            query(f'CREATE DATABASE `{scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
            created = True
            with dump.open('rb') as file:
                db(['mariadb', '--user=root', scratch], stdin=file, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            assert snapshot(scratch, tables) == before
            checks = query('CHECK TABLE ' + ','.join(f'`{scratch}`.`{t}`' for t in tables) + ';').splitlines()
            assert len(checks) == len(tables) and all(row.endswith('status\tOK') for row in checks)
            assert snapshot(TARGET, tables) == before
        finally:
            if created:
                query(f'DROP DATABASE `{scratch}`;')
        proof = {'status': 'BACKUP VERIFIED', 'created_at_utc': now.isoformat(), 'target_database': TARGET,
                 'target_fingerprint': plan['target_before'], 'filename': dump.name, 'private_relative_path': str(dump.relative_to(ROOT)),
                 'size_bytes': len(content), 'sha256': hashlib.sha256(content).hexdigest(), 'tables': len(tables),
                 'restore_counts_checksums_schema_match': True, 'all_tables_check_ok': True, 'scratch_removed': True}
        (PRIVATE / 'backup.json').write_text(json.dumps(proof, indent=2) + '\n')
        os.chmod(PRIVATE / 'backup.json', 0o600)
        result = json.loads(app('scripts/phase4/repair_content_urls.php', '--apply'))
        after = snapshot(TARGET, tables)
        changed_tables = [t for t in tables if before[t] != after[t]]
        assert set(changed_tables) <= {'products', 'categories', 'article_categories', 'articles'}
        assert all(before[t]['rows'] == after[t]['rows'] and before[t]['schema_sha256'] == after[t]['schema_sha256'] for t in tables)
        reconciliation = json.loads(app('artisan', 'legacy:validate').strip().splitlines()[-1])
        assert reconciliation['status'] == 'PASS'
        result.update(checked_at_utc=now.isoformat(), backup=proof, changed_tables=changed_tables,
                      all_row_counts_and_schemas_unchanged=True, non_content_tables_checksums_unchanged=True, reconciliation='PASS')
        (OUT / 'content-risk-closure.json').write_text(json.dumps(result, indent=2) + '\n')
        (OUT / 'content-reconciliation.json').write_text(json.dumps(reconciliation, indent=2) + '\n')
        print('PASS:', result['changed_fields'], 'content fields repaired; fresh backup/restore verified;', len(tables), 'table counts/schema preserved; source/history/inventory/publication preserved; reconciliation PASS.')
    finally:
        app('artisan', 'up')


if __name__ == '__main__':
    main()
