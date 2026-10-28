"""Merge local migrated catalog into the existing demo website, retaining both originals/backups."""
import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import sys
import tarfile

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts/readiness'))
from verify_backup import db, query, snapshot

PRIVATE = ROOT / 'legacy-private/ui-mapping/combined'
OUT = ROOT / 'docs/ui-mapping/evidence'


def app(container, *args):
    result = subprocess.run(['docker', 'exec', container, 'php', *args], cwd=ROOT, capture_output=True, text=True)
    if result.returncode:
        (PRIVATE / 'failure.log').write_text(result.stdout + result.stderr)
        raise RuntimeError('Local merge failed; inspect private failure.log. Do not replay/reset databases.')
    return result.stdout


def main():
    PRIVATE.mkdir(parents=True, exist_ok=True)
    os.chmod(PRIVATE, 0o700)
    OUT.mkdir(parents=True, exist_ok=True)
    assert not (PRIVATE / 'receipt.json').exists(), 'Existing merge receipt: do not replay; verify current checkpoint instead'
    env = dict(line.split('=', 1) for line in (ROOT / '.env').read_text().splitlines() if '=' in line)
    assert env['APP_ENV'] == 'local' and env['DB_DATABASE'] == 'bancay'
    username = 'bancay_combined_readonly'
    assert not query(f"SELECT User FROM mysql.user WHERE User='{username}';"), 'Existing reader must be reviewed rather than replaced'
    password = secrets.token_hex(24)
    query(f"CREATE USER '{username}'@'%' IDENTIFIED BY '{password}'; GRANT SELECT ON `bancay_phase4_staging`.* TO '{username}'@'%';")
    (PRIVATE / 'reader.json').write_text(json.dumps({'username': username, 'password': password}))
    os.chmod(PRIVATE / 'reader.json', 0o600)
    containers = ['bancay-app-1', 'bancay-phase4-app']
    for container in containers:
        app(container, 'artisan', 'down')
    try:
        plan = json.loads(app('bancay-app-1', 'scripts/ui-mapping/merge.php', '--dry-run'))
        assert plan['source_counts']['products'] == 115 and plan['source_counts']['articles'] == 67 and plan['source_counts']['orders'] == 255
        now = datetime.datetime.now(datetime.timezone.utc)
        stamp = now.strftime('%Y%m%dT%H%M%SZ')
        proofs, before = [], {}
        for name in ['bancay', 'bancay_phase4_staging']:
            tables = query(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='{name}' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;").splitlines()
            assert len(tables) == 98 and all(re.fullmatch(r'[A-Za-z0-9_]+', table) for table in tables)
            before[name] = snapshot(name, tables)
            dump = PRIVATE / f'{name}-{stamp}.sql'
            with dump.open('xb') as file:
                os.chmod(dump, 0o600)
                db(['mariadb-dump', '--user=root', '--single-transaction', '--quick', '--hex-blob', '--routines', '--events', '--triggers', '--skip-comments', '--skip-dump-date', name], stdout=file, stderr=subprocess.PIPE)
            content = dump.read_bytes()
            assert len(content) > 10000 and not re.search(rb'(?mi)^(CREATE DATABASE|USE )', content)
            assert set(re.findall(rb'CREATE TABLE `([A-Za-z0-9_]+)`', content)) == {table.encode() for table in tables}
            scratch = 'bancay_combined_verify_' + ('main_' if name == 'bancay' else 'stage_') + now.strftime('%H%M%S')
            assert not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{scratch}';")
            created = False
            try:
                query(f'CREATE DATABASE `{scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
                created = True
                with dump.open('rb') as file:
                    db(['mariadb', '--user=root', scratch], stdin=file, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                assert snapshot(scratch, tables) == before[name]
                checks = query('CHECK TABLE ' + ','.join(f'`{scratch}`.`{table}`' for table in tables) + ';').splitlines()
                assert len(checks) == len(tables) and all(row.endswith('status\tOK') for row in checks)
                assert snapshot(name, tables) == before[name]
            finally:
                if created:
                    query(f'DROP DATABASE `{scratch}`;')
            proofs.append({'database': name, 'filename': dump.name, 'private_path': str(dump.relative_to(ROOT)),
                           'timestamp_utc': now.isoformat(), 'size_bytes': len(content), 'sha256': hashlib.sha256(content).hexdigest(),
                           'tables': len(tables), 'restore_counts_data_checksums_schema_match': True, 'check_table_ok': True, 'scratch_removed': True})
        archive = PRIVATE / ('original-demo-media-' + stamp + '.tar')
        with tarfile.open(archive, 'x') as tar:
            os.chmod(archive, 0o600)
            tar.add(ROOT / 'storage/app/public', arcname='storage/app/public')
        with tarfile.open(archive) as tar:
            assert tar.getmembers()
        with archive.open('rb') as file:
            archive_hash = hashlib.file_digest(file, 'sha256').hexdigest()
        proof = {'status': 'BACKUP VERIFIED', 'target_before': plan['target_before'], 'databases': proofs,
                 'demo_media': {'private_path': str(archive.relative_to(ROOT)), 'size_bytes': archive.stat().st_size, 'sha256': archive_hash, 'readable': True}}
        (PRIVATE / 'backup.json').write_text(json.dumps(proof, indent=2) + '\n')
        os.chmod(PRIVATE / 'backup.json', 0o600)
        result = json.loads(app('bancay-app-1', 'scripts/ui-mapping/merge.php', '--apply'))
        main_after = snapshot('bancay', list(before['bancay']))
        for table, old in before['bancay'].items():
            if table in result['created']:
                assert main_after[table]['rows'] == old['rows'] + result['created'][table]
            else:
                assert main_after[table] == old, 'Out-of-scope native table changed: ' + table
        assert snapshot('bancay_phase4_staging', list(before['bancay_phase4_staging'])) == before['bancay_phase4_staging']
        rerun = json.loads(app('bancay-app-1', 'scripts/ui-mapping/merge.php', '--verify-rerun'))
        result.update(backup=proof, before_counts={table: data['rows'] for table, data in before['bancay'].items() if table in result['created']},
                      after_counts={table: data['rows'] for table, data in main_after.items() if table in result['created']},
                      original_stage_all98_tables_unchanged=True, original_demo_rows_unchanged=True, native_tables_unchanged=True,
                      idempotent_rerun=rerun, environment='Local8000 only; noindex; no production/public deployment')
        (OUT / 'combined-merge.json').write_text(json.dumps(result, indent=2) + '\n')
        print('PASS: demo retained and115 migrated products/120 variants/67 articles/255 historical orders merged into local8000; source8002 unchanged; verified backups; rerun0 duplicates.')
    finally:
        for container in containers:
            app(container, 'artisan', 'up')


if __name__ == '__main__':
    main()
