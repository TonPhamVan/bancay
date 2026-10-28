"""Own a disposable clone of the verified local synthetic backup for browser tests."""
import datetime
import json
import os
from pathlib import Path
import re
import subprocess
from verify_backup import ROOT, OUT, PRIVATE, db, query

STATE = PRIVATE / 'browser-state.json'
NAME = 'bancay-readiness-browser'


def main():
    import sys
    if len(sys.argv) != 2 or sys.argv[1] not in ['start', 'stop']:
        raise SystemExit('Usage: browser_environment.py start|stop')
    if sys.argv[1] == 'stop':
        state = json.loads(STATE.read_text())
        database = state['database']
        assert re.fullmatch(r'bancay_readiness_browser_[0-9]{8}_[0-9]{6}', database)
        subprocess.run(['docker', 'stop', NAME], cwd=ROOT, check=True, stdout=subprocess.PIPE)
        query(f"REVOKE ALL PRIVILEGES ON `{database}`.* FROM 'bancay'@'%';")
        query(f'DROP DATABASE `{database}`;')
        state['cleaned_up'] = True
        STATE.write_text(json.dumps(state, indent=2) + '\n')
        (OUT / 'browser-environment.json').write_text(json.dumps(state, indent=2) + '\n')
        print('Browser container stopped; owned clone/grant removed; operational DB not used for checkout.')
        return
    if STATE.exists() and not json.loads(STATE.read_text()).get('cleaned_up'):
        raise RuntimeError('An existing owned browser clone must be cleaned up first')
    backup = json.loads((OUT / 'backup-verification.json').read_text())
    assert backup['status'] == 'BACKUP VERIFIED'
    dump = ROOT / backup['private_relative_path']
    import hashlib
    assert hashlib.sha256(dump.read_bytes()).hexdigest() == backup['sha256']
    database = 'bancay_readiness_browser_' + datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S')
    assert not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{database}';")
    query(f'CREATE DATABASE `{database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    with dump.open('rb') as file:
        db(['mariadb', '--user=root', database], stdin=file, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    query(f"GRANT ALL PRIVILEGES ON `{database}`.* TO 'bancay'@'%';")
    query(f"UPDATE `{database}`.`settings` SET `value`=JSON_QUOTE('Readiness QA local clone') WHERE `key`='site_name';")
    env = dict(line.split('=', 1) for line in (ROOT / '.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
    env = {k: v.strip().strip('\"').strip("'") for k, v in env.items()}
    env.update(APP_ENV='local', APP_DEBUG='false', APP_URL='http://localhost:8001', DB_HOST='db', DB_DATABASE=database, MAIL_MAILER='log', SESSION_COOKIE='bancay_readiness_browser')
    envfile = PRIVATE / 'browser.env'
    envfile.write_text('\n'.join(k + '=' + v for k, v in env.items()) + '\n')
    os.chmod(envfile, 0o600)
    subprocess.run(['docker', 'run', '-d', '--rm', '--name', NAME, '--network', 'bancay_default', '--user', '1000:1000', '--env-file', str(envfile), '-p', '127.0.0.1:8001:8000', '-v', str(ROOT)+':/var/www/html', '-w', '/var/www/html', 'bancay-php:8.3', 'php', 'artisan', 'serve', '--host=0.0.0.0', '--port=8000', '--no-reload'], cwd=ROOT, check=True, stdout=subprocess.PIPE)
    state = {'database': database, 'container': NAME, 'base_url': 'http://localhost:8001', 'source': 'verified current local synthetic backup; not legacy or production data', 'cleaned_up': False}
    STATE.write_text(json.dumps(state, indent=2) + '\n')
    print('Isolated browser build started at localhost:8001; synthetic backup clone only.')


if __name__ == '__main__':
    main()
