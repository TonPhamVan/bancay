"""Clone migrated local staging for publication/checkout acceptance; preserve actual import as DRAFT."""
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import sys
ROOT=Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT/'scripts/readiness'))
from verify_backup import db,query
P=ROOT/'legacy-private/phase4'
QA=P/'ui'
TARGET='bancay_phase4_ui'
NAME='bancay-phase4-ui'


def main():
    if sys.argv[1:] == ['stop']:
        assert TARGET=='bancay_phase4_ui'
        subprocess.run(['docker','rm','-f',NAME],cwd=ROOT,check=True,stdout=subprocess.PIPE)
        query(f"DROP DATABASE `{TARGET}`; DROP USER 'bancay_p4_ui'@'%';")
        print('Disposable UI clone/container/account removed; primary staging remains hidden.')
        return
    assert sys.argv[1:] in [['start'], ['start-combined']]
    combined = sys.argv[1:] == ['start-combined']
    source_database = 'bancay' if combined else 'bancay_phase4_staging'
    assert not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{TARGET}';")
    QA.mkdir(exist_ok=True)
    dump=QA/('stage-copy-'+__import__('datetime').datetime.now(__import__('datetime').timezone.utc).strftime('%Y%m%d%H%M%S')+'.sql')
    with dump.open('xb') as file:
        os.chmod(dump,0o600)
        db(['mariadb-dump','--user=root','--single-transaction','--quick','--skip-comments','--skip-dump-date',source_database],stdout=file,stderr=subprocess.PIPE)
    query(f'CREATE DATABASE `{TARGET}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    with dump.open('rb') as file:db(['mariadb','--user=root',TARGET],stdin=file,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    password=secrets.token_hex(24)
    query(f"CREATE USER 'bancay_p4_ui'@'%' IDENTIFIED BY '{password}'; GRANT ALL PRIVILEGES ON `{TARGET}`.* TO 'bancay_p4_ui'@'%';")
    env=dict(line.split('=',1) for line in ((ROOT/'.env') if combined else (P/'staging.env')).read_text().splitlines() if '=' in line)
    if combined:
        credentials=dict(line.split('=',1) for line in (ROOT/'legacy-private/phase1/admin.env').read_text().splitlines() if '=' in line)
        env['BANCAY_BOOTSTRAP_PASSWORD']=credentials['BANCAY_BOOTSTRAP_PASSWORD']
    env['BANCAY_QA_DATASET']='combined' if combined else 'migrated'
    env.update(DB_DATABASE=TARGET,DB_USERNAME='bancay_p4_ui',DB_PASSWORD=password,APP_URL='http://localhost:8003',LEGACY_MIGRATION_ENABLED='false',SESSION_COOKIE='bancay_phase4_ui')
    envfile=QA/'staging.env';envfile.write_text('\n'.join(k+'='+v for k,v in env.items())+'\n');os.chmod(envfile,0o600)
    storage=QA/'storage'
    for folder in ['app/public','app/plugins','framework/cache/data','framework/sessions','framework/views','logs']:(storage/folder).mkdir(parents=True,exist_ok=True)
    source_storage = ROOT/'storage' if combined else P/'storage'
    shutil.copytree(source_storage/'app/plugins',storage/'app/plugins',dirs_exist_ok=True)
    subprocess.run(['docker','run','-d','--name',NAME,'--network','bancay_default','--user','1000:1000','--env-file',str(envfile),'-p','127.0.0.1:8003:8000','-v',str(ROOT)+':/var/www/html','-v',str(storage)+':/var/www/html/storage','-v',str(source_storage/'app/public')+':/var/www/html/storage/app/public:ro','-w','/var/www/html','bancay-php:8.3','php','artisan','serve','--host=0.0.0.0','--port=8000','--no-reload'],cwd=ROOT,check=True,stdout=subprocess.PIPE)
    for command in ['cache:clear', 'view:clear']:
        subprocess.run(['docker', 'exec', NAME, 'php', 'artisan', command], cwd=ROOT, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    r=subprocess.run(['docker','exec',NAME,'php','scripts/phase4/prepare_ui.php'],cwd=ROOT,capture_output=True,text=True)
    (QA/'prepare.log').write_text(r.stdout+r.stderr)
    assert r.returncode==0,'QA fixture setup failed; inspect private log'
    print('Owned QA clone ready atlocalhost8003; real catalog data, synthetic checkout contact only.')


if __name__=='__main__':main()
