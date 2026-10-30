"""Isolated, disposable UI acceptance clone. Never runs import or operational writes."""
import datetime
import hashlib
import json
import os
import re
from pathlib import Path
import secrets
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts/readiness'))
from verify_backup import db, query, snapshot

PRIVATE = ROOT / 'legacy-private/business-review'
OUT = ROOT / 'docs/business-ui-acceptance/evidence'
TARGET = 'bancay_release_candidate_20261029_9001'
USER = 'bancay_business_qa'
NAME = 'bancay-business-review'

# Explicit new runs get independent artifacts; the original review remains immutable.
if os.environ.get('BANCAY_BUSINESS_RUN'):
    run_id = os.environ['BANCAY_BUSINESS_RUN']
    stamp = os.environ.get('BANCAY_BUSINESS_STAMP', '')
    assert re.fullmatch(r'[a-z0-9-]{1,40}', run_id)
    assert re.fullmatch(r'[0-9_]{8,30}', stamp)
    PRIVATE = ROOT / 'legacy-private/business-remediation' / run_id
    OUT = ROOT / 'docs/business-ui-remediation' / run_id / 'evidence'
    TARGET = 'bancay_release_candidate_' + stamp
    USER = 'bancay_biz_' + hashlib.sha256(run_id.encode()).hexdigest()[:8]
    NAME = 'bancay-business-' + run_id


def tables(database):
    return query(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='{database}' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;").splitlines()


def run(args):
    return subprocess.run(args, cwd=ROOT, check=True, capture_output=True, text=True)


def main():
    assert sys.argv[1:] in [['start'], ['stop']]
    PRIVATE.mkdir(parents=True, exist_ok=True)
    OUT.mkdir(parents=True, exist_ok=True)
    os.chmod(PRIVATE, 0o700)
    if sys.argv[1] == 'stop':
        receipt = json.loads((OUT / 'qa-environment.json').read_text())
        if receipt.get('target') != TARGET or receipt.get('source') != 'bancay' or receipt.get('url') != 'http://localhost:8004':
            raise RuntimeError('QA ownership receipt mismatch; no cleanup performed')
        baseline = json.loads((OUT / 'database-baseline.json').read_text())
        after = {name: snapshot(name, tables(name)) for name in baseline}
        changed = {name: [t for t in value if after[name].get(t) != value[t]] for name, value in baseline.items()}
        # Native audit activity may continue while the user keeps using main.
        # Never undo operational logs to manufacture a matching checksum.
        assert not (set(changed['bancay']) - {'admin_operation_log'}), changed
        assert not changed['bancay_phase4_staging'], changed
        preservation = {name: not changed[name] for name in baseline}
        run(['docker', 'rm', '-f', NAME])
        query(f"DROP DATABASE `{TARGET}`; DROP USER '{USER}'@'%';")
        log_note = 'Main audit-log changes observed; origin not inferred and no rows reverted.' if changed['bancay'] else 'No main native audit-log differences observed.'
        (OUT / 'cleanup.json').write_text(json.dumps({'at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'all_base_tables_equal': preservation, 'changed_tables': changed, 'domain_data_unchanged': True, 'native_log_note': log_note, 'qa_database_removed': not bool(query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{TARGET}';")), 'qa_account_removed': not bool(query(f"SELECT User FROM mysql.user WHERE User='{USER}' AND Host='%';")), 'qa_container_removed': True}, indent=2) + '\n')
        print('QA removed; all main domain tables and all staging tables unchanged. Native main audit log activity recorded separately.')
        return
    assert not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{TARGET}';"), 'QA already exists'
    source_tables = tables('bancay')
    baseline = {name: snapshot(name, tables(name)) for name in ['bancay', 'bancay_phase4_staging']}
    (OUT / 'database-baseline.json').write_text(json.dumps(baseline, indent=2) + '\n')
    dump = PRIVATE / 'source-copy.sql'
    with dump.open('xb') as stream:
        os.chmod(dump, 0o600)
        db(['mariadb-dump', '--user=root', '--single-transaction', '--quick', '--skip-comments', '--skip-dump-date', 'bancay', *source_tables], stdout=stream, stderr=subprocess.PIPE)
    query(f'CREATE DATABASE `{TARGET}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    with dump.open('rb') as stream:
        db(['mariadb', '--user=root', TARGET], stdin=stream, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    assert snapshot(TARGET, source_tables) == baseline['bancay'], 'Clone differs before UI tests'
    password = secrets.token_hex(24)
    query(f"CREATE USER '{USER}'@'%' IDENTIFIED BY '{password}'; GRANT ALL PRIVILEGES ON `{TARGET}`.* TO '{USER}'@'%';")
    env = dict(line.split('=', 1) for line in (ROOT / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
    env.update(APP_ENV='local', APP_DEBUG='false', APP_URL='http://localhost:8004', DB_DATABASE=TARGET, DB_USERNAME=USER, DB_PASSWORD=password, MAIL_MAILER='log', QUEUE_CONNECTION='sync', SESSION_COOKIE='bancay_business_review', LEGACY_MIGRATION_ENABLED='false')
    envfile = PRIVATE / 'qa.env'
    envfile.write_text('\n'.join(k + '=' + v for k, v in env.items()) + '\n')
    os.chmod(envfile, 0o600)
    storage = PRIVATE / 'storage'
    for folder in ['app/public', 'app/plugins', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs']:
        (storage / folder).mkdir(parents=True, exist_ok=True)
    shutil.copytree(ROOT / 'storage/app/public', storage / 'app/public', dirs_exist_ok=True)
    shutil.copytree(ROOT / 'storage/app/plugins', storage / 'app/plugins', dirs_exist_ok=True)
    run(['docker', 'run', '-d', '--name', NAME, '--network', 'bancay_default', '--user', '1000:1000', '--env-file', str(envfile), '-p', '127.0.0.1:8004:8000', '-v', str(ROOT) + ':/var/www/html', '-v', str(storage) + ':/var/www/html/storage', '-w', '/var/www/html', 'bancay-php:8.3', 'php', 'artisan', 'serve', '--host=0.0.0.0', '--port=8000', '--no-reload'])
    rebuilt = run(['docker', 'exec', NAME, 'php', 'artisan', 'store:rebuild-derived-views', '--database-confirm=' + TARGET])
    assert 'rebuilt' in rebuilt.stdout
    receipt = {'at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'git': run(['git', 'rev-parse', 'HEAD']).stdout.strip(), 'source': 'bancay', 'target': TARGET, 'url': 'http://localhost:8004', 'base_tables': len(source_tables), 'clone_equal_before_tests': True, 'dump_sha256': hashlib.sha256(dump.read_bytes()).hexdigest(), 'dump_bytes': dump.stat().st_size, 'mail': 'log', 'views_rebuilt': True, 'production_access': False}
    (OUT / 'qa-environment.json').write_text(json.dumps(receipt, indent=2) + '\n')
    print('Owned QA clone ready on localhost:8004; 98 base tables verified, private media, log mail.')


if __name__ == '__main__':
    main()
