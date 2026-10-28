"""Apply approved review decisions with fresh dump/restore, matching dry-run and reconciliation."""
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
PRIVATE = ROOT / 'legacy-private/phase4/review-application'
OUT = ROOT / 'docs/phase-4/evidence'


def app(*args):
    result = subprocess.run(['docker', 'exec', 'bancay-phase4-app', 'php', *args], cwd=ROOT, capture_output=True, text=True)
    if result.returncode:
        PRIVATE.mkdir(parents=True, exist_ok=True)
        (PRIVATE / 'failure.log').write_text(result.stdout + result.stderr)
        raise RuntimeError('Guarded operation failed; inspect private failure.log')
    return result.stdout


def schema_shape(database, tables):
    result = {}
    for line in query('\n'.join(f'SHOW CREATE TABLE `{database}`.`{t}`;' for t in tables)).splitlines():
        name, ddl = line.split('\t', 1)
        # Inserted rows legitimately advance this table option; all structural DDL remains exact.
        result[name] = hashlib.sha256(re.sub(r'AUTO_INCREMENT=[0-9]+', 'AUTO_INCREMENT=<COUNTER>', ddl).encode()).hexdigest()
    return result


def verify_result(before, before_shape, tables, proof, result):
    after = snapshot(TARGET, tables)
    changed_tables = [t for t in tables if before[t] != after[t]]
    assert set(changed_tables) <= {'products', 'product_images', 'product_options', 'product_option_values', 'product_variants', 'product_variant_values', 'inventories', 'articles'}
    assert before_shape == schema_shape(TARGET, tables), 'Structural DDL changed beyond AUTO_INCREMENT counters'
    for table, delta in [('products', 2), ('articles', 7), ('product_variants', 2), ('inventories', 2)]:
        assert after[table]['rows'] == before[table]['rows'] + delta
    assert all(before[t] == after[t] for t in ['orders', 'order_items', 'order_status_histories'])
    reconciliation = json.loads(app('artisan', 'legacy:validate').strip().splitlines()[-1])
    assert reconciliation['status'] == 'PASS'
    rerun = json.loads(app('scripts/phase4/apply_review_decisions.php', '--verify-rerun'))
    result.update(checked_at_utc=datetime.datetime.now(datetime.timezone.utc).isoformat(), backup=proof,
                  changed_tables=changed_tables, structural_schemas_unchanged=True,
                  schema_comparison='Ignore only expected AUTO_INCREMENT table counters from approved inserts',
                  historical_tables_checksums_unchanged=True, out_of_scope_tables_checksums_unchanged=True,
                  reconciliation_status='PASS', idempotent_rerun=rerun)
    (OUT / 'review-application.json').write_text(json.dumps(result, indent=2) + '\n')
    (OUT / 'review-reconciliation.json').write_text(json.dumps(reconciliation, indent=2) + '\n')
    print('PASS: approved2 products/7 articles imported; media repairs', result['media_repairs'], '; backup/restore87 tables verified; historical checksums unchanged; reconciliation/rerun PASS.')


def main():
    PRIVATE.mkdir(parents=True, exist_ok=True)
    os.chmod(PRIVATE, 0o700)
    env = dict(line.split('=', 1) for line in (ROOT / 'legacy-private/phase4/staging.env').read_text().splitlines() if '=' in line)
    assert env['APP_ENV'] == 'local' and env['DB_DATABASE'] == TARGET and env['DB_HOST'] == 'db'
    verify_existing = sys.argv[1:] == ['--verify-existing']
    assert verify_existing or not sys.argv[1:]
    app('artisan', 'down')
    try:
        if verify_existing:
            proof = json.loads((PRIVATE / 'backup.json').read_text())
            assert proof['status'] == 'BACKUP VERIFIED' and proof['target_database'] == TARGET
            dump = ROOT / proof['private_relative_path']
            assert dump.resolve().is_relative_to(PRIVATE.resolve())
            assert hashlib.sha256(dump.read_bytes()).hexdigest() == proof['sha256']
            content = dump.read_bytes()
            tables = sorted(t.decode() for t in re.findall(rb'CREATE TABLE `([A-Za-z0-9_]+)`', content))
        else:
            plan = json.loads(app('scripts/phase4/apply_review_decisions.php', '--dry-run'))
            # This operation is specifically a nine-record approved addition, not a reset/upsert tool.
            assert plan['counts']['products']['CREATED'] == 2 and plan['counts']['articles']['CREATED'] == 7
            now = datetime.datetime.now(datetime.timezone.utc)
            dump = PRIVATE / (TARGET + '-' + now.strftime('%Y%m%dT%H%M%SZ') + '.sql')
            tables = query(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='{TARGET}' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;").splitlines()
            assert len(tables) >= 18 and all(re.fullmatch(r'[a-zA-Z0-9_]+', t) for t in tables)
            before = snapshot(TARGET, tables)
            before_shape = schema_shape(TARGET, tables)
            with dump.open('xb') as file:
                os.chmod(dump, 0o600)
                db(['mariadb-dump', '--user=root', '--single-transaction', '--quick', '--hex-blob', '--routines', '--events', '--triggers', '--skip-comments', '--skip-dump-date', TARGET], stdout=file, stderr=subprocess.PIPE)
            content = dump.read_bytes()
            assert len(content) > 10000 and not re.search(rb'(?mi)^(CREATE DATABASE|USE )', content)
            assert set(re.findall(rb'CREATE TABLE `([A-Za-z0-9_]+)`', content)) == {t.encode() for t in tables}
        scratch = 'bancay_review_verify_' + datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S')
        assert re.fullmatch(r'bancay_review_verify_[0-9]{8}_[0-9]{6}', scratch)
        assert not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{scratch}';")
        created = False
        try:
            query(f'CREATE DATABASE `{scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
            created = True
            with dump.open('rb') as file:
                db(['mariadb', '--user=root', scratch], stdin=file, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            restored = snapshot(scratch, tables)
            restored_shape = schema_shape(scratch, tables)
            if verify_existing:
                before, before_shape = restored, restored_shape
            else:
                assert restored == before and restored_shape == before_shape
                assert snapshot(TARGET, tables) == before
            checks = query('CHECK TABLE ' + ','.join(f'`{scratch}`.`{t}`' for t in tables) + ';').splitlines()
            assert len(checks) == len(tables) and all(row.endswith('status\tOK') for row in checks)
        finally:
            if created:
                query(f'DROP DATABASE `{scratch}`;')
        if verify_existing:
            result = json.loads((PRIVATE / 'result.json').read_text())
        else:
            proof = {'status': 'BACKUP VERIFIED', 'created_at_utc': now.isoformat(), 'target_database': TARGET,
                     'target_fingerprint': plan['target_before'], 'filename': dump.name, 'private_relative_path': str(dump.relative_to(ROOT)),
                     'size_bytes': len(content), 'sha256': hashlib.sha256(content).hexdigest(), 'tables': len(tables),
                     'restore_counts_checksums_schema_match': True, 'all_tables_check_ok': True, 'scratch_removed': True}
            (PRIVATE / 'backup.json').write_text(json.dumps(proof, indent=2) + '\n')
            os.chmod(PRIVATE / 'backup.json', 0o600)
            result = json.loads(app('scripts/phase4/apply_review_decisions.php', '--apply'))
        verify_result(before, before_shape, tables, proof, result)
    finally:
        app('artisan', 'up')


if __name__ == '__main__':
    main()
