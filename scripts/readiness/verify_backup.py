"""Back up local bancay and verify restore in a newly owned disposable database."""
import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[2]
SOURCE = 'bancay'
OUT = ROOT / 'docs/readiness-closure/evidence'
PRIVATE = ROOT / 'legacy-private/readiness-closure'


def db(args, **kwargs):
    return subprocess.run(['docker', 'compose', 'exec', '-T', 'db', 'sh', '-c', 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" exec "$@"', 'sh', *args], cwd=ROOT, check=True, **kwargs)


def query(sql):
    return db(['mariadb', '--user=root', '--batch', '--skip-column-names'], input=sql.encode(), capture_output=True).stdout.decode().rstrip('\n')


def snapshot(database, tables):
    counts = query(' UNION ALL '.join(f"SELECT '{t}', COUNT(*) FROM `{database}`.`{t}`" for t in tables) + ';')
    result = {name: {'rows': int(count)} for name, count in (line.split('\t') for line in counts.splitlines())}
    checksums = query('CHECKSUM TABLE ' + ','.join(f'`{database}`.`{t}`' for t in tables) + ' EXTENDED;')
    for line in checksums.splitlines():
        name, checksum = line.split('\t')
        result[name.split('.', 1)[1]]['checksum'] = checksum
    schemas = query('\n'.join(f'SHOW CREATE TABLE `{database}`.`{t}`;' for t in tables))
    for line in schemas.splitlines():
        name, ddl = line.split('\t', 1)
        result[name]['schema_sha256'] = hashlib.sha256((name + '\t' + ddl).encode()).hexdigest()
    return result


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    PRIVATE.mkdir(parents=True, exist_ok=True)
    os.chmod(PRIVATE, 0o700)
    # Refuse a different environment; this script never reads or restores legacy data.
    env = dict(line.split('=', 1) for line in (ROOT / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
    assert env.get('APP_ENV') == 'local' and env.get('DB_DATABASE') == SOURCE
    tables = query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='bancay' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;").splitlines()
    assert len(tables) >= 18 and all(re.fullmatch(r'[A-Za-z0-9_]+', t) for t in tables)
    required = {'products', 'product_variants', 'inventories', 'orders', 'order_items', 'order_status_histories', 'migrations'}
    assert required <= set(tables)
    before = snapshot(SOURCE, tables)
    now = datetime.datetime.now(datetime.timezone.utc)
    stamp = now.strftime('%Y%m%dT%H%M%SZ')
    dump = PRIVATE / f'bancay-{stamp}.sql'
    assert not dump.exists()
    with dump.open('xb') as file:
        os.chmod(dump, 0o600)
        db(['mariadb-dump', '--user=root', '--single-transaction', '--quick', '--routines', '--events', '--triggers', '--hex-blob', '--skip-comments', '--skip-dump-date', SOURCE], stdout=file, stderr=subprocess.PIPE)
    content = dump.read_bytes()
    digest = hashlib.sha256(content).hexdigest()
    assert len(content) > 10000 and not re.search(rb'(?mi)^(CREATE DATABASE|USE )', content)
    declared = set(re.findall(rb'CREATE TABLE `([A-Za-z0-9_]+)`', content))
    assert {t.encode() for t in tables} == declared
    scratch = 'bancay_restore_verify_' + now.strftime('%Y%m%d_%H%M%S')
    assert re.fullmatch(r'bancay_restore_verify_[0-9]{8}_[0-9]{6}', scratch) and scratch not in [SOURCE, 'bancay_test']
    assert not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{scratch}';")
    created = False
    try:
        query(f'CREATE DATABASE `{scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
        created = True
        with dump.open('rb') as file:
            db(['mariadb', '--user=root', scratch], stdin=file, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        restored = snapshot(scratch, tables)
        assert restored == before, 'Restored counts/data checksums/schema differ from source snapshot'
        checks = query('CHECK TABLE ' + ','.join(f'`{scratch}`.`{t}`' for t in tables) + ';').splitlines()
        assert len(checks) == len(tables) and all(line.endswith('status\tOK') for line in checks)
        after = snapshot(SOURCE, tables)
        assert after == before, 'Source changed during verification: ' + ','.join(t for t in tables if after[t] != before[t])
        assert hashlib.sha256(dump.read_bytes()).hexdigest() == digest
    finally:
        if created:
            query(f'DROP DATABASE `{scratch}`;')
    result = {'status': 'BACKUP VERIFIED', 'created_at_utc': now.isoformat(), 'source_database': SOURCE, 'source_environment': 'local synthetic application DB; no legacy or production connection', 'filename': dump.name, 'private_relative_path': str(dump.relative_to(ROOT)), 'size_bytes': len(content), 'sha256': digest, 'file_mode': oct(dump.stat().st_mode & 0o777), 'dump_readable': True, 'tables_in_dump': len(tables), 'required_tables_present': sorted(required), 'restore': {'status': 'PASS', 'target_database': scratch, 'source_writes': False, 'counts_data_checksums_schema_match': True, 'all_tables_check_ok': True, 'source_unchanged': True, 'scratch_removed': True}, 'tables': before}
    (OUT / 'backup-verification.json').write_text(json.dumps(result, indent=2) + '\n')
    print('BACKUP VERIFIED:', dump.name, len(content), 'bytes;', len(tables), 'tables; restore/checksums/schema/source unchanged PASS; scratch removed.')


if __name__ == '__main__':
    main()
