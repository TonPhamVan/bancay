"""Prepare only owned local Phase4 source snapshot and staging, verify all supplied backups."""
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts/readiness'))
from verify_backup import db, query, snapshot
PRIVATE = ROOT / 'legacy-private/phase4'
OUT = ROOT / 'docs/phase-4/evidence'
SOURCE = 'bancay_phase4_source'
TARGET = 'bancay_phase4_staging'
CONTAINER = 'bancay-phase4-app'


def hash_file(path):
    with path.open('rb') as file:
        return hashlib.file_digest(file, 'sha256').hexdigest()


def app(args):
    result = subprocess.run(['docker', 'exec', CONTAINER, *args], cwd=ROOT, capture_output=True)
    (PRIVATE / ('setup-' + str(app.counter) + '.log')).write_bytes(result.stdout + result.stderr)
    app.counter += 1
    if result.returncode:
        raise RuntimeError('Stage setup command failed; inspect private log ' + str(app.counter - 1))
app.counter = 1


def main():
    PRIVATE.mkdir(parents=True, exist_ok=True)
    os.chmod(PRIVATE, 0o700)
    OUT.mkdir(parents=True, exist_ok=True)
    assert not (PRIVATE / 'environment.json').exists(), 'Existing owned profile: inspect/reuse, never silently reset'
    original = ROOT / 'backups/phase-0/2026-10-27'
    manifest = json.loads((original / 'manifest.private.json').read_text())
    for item in manifest['artifacts']:
        path = original / item['name']
        assert path.stat().st_size == item['bytes'] and hash_file(path) == item['sha256']
    with zipfile.ZipFile(original / 'bancay.zip') as archive:
        assert archive.testzip() is None
    extraction = ROOT / 'legacy-private/phase0/extracted'
    for item in manifest['extracted_sha256_manifest']:
        path = extraction / item['path']
        assert path.is_file() and path.stat().st_size == item['bytes'] and hash_file(path) == item['sha256']
    sql = original / 'caybonmua_db.sql'
    assert not re.search(rb'(?mi)^(USE |CREATE DATABASE|DROP DATABASE|GRANT |REVOKE |CREATE USER)', sql.read_bytes())
    assert not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('{SOURCE}','{TARGET}');")
    query(f'CREATE DATABASE `{SOURCE}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    with sql.open('rb') as file:
        db(['mariadb', '--user=root', SOURCE], stdin=file, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    expected = {'tbl_count':1,'tbladmin':2,'tblbaiviet':67,'tblbanner':15,'tblchuyenmuc':11,'tbldanhmuc':8,'tblfooter':1,'tblkhachhang':0,'tblorder':748,'tblrole':4,'tblsanpham':115,'tblslider':8}
    assert {t:int(query(f'SELECT COUNT(*) FROM `{SOURCE}`.`{t}`;')) for t in expected} == expected
    reader_password = secrets.token_hex(24)
    writer_password = secrets.token_hex(24)
    bootstrap = secrets.token_urlsafe(30)
    query(f"CREATE USER 'bancay_p4_reader'@'%' IDENTIFIED BY '{reader_password}'; GRANT SELECT ON `{SOURCE}`.* TO 'bancay_p4_reader'@'%';")
    query(f"CREATE DATABASE `{TARGET}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER 'bancay_p4_stage'@'%' IDENTIFIED BY '{writer_password}'; GRANT ALL PRIVILEGES ON `{TARGET}`.* TO 'bancay_p4_stage'@'%';")
    storage = PRIVATE / 'storage'
    for folder in ['app/public', 'app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs']:
        (storage / folder).mkdir(parents=True, exist_ok=True)
    (PRIVATE / 'reports').mkdir(exist_ok=True)
    env = {'APP_NAME':'bancay Phase4 local staging', 'APP_ENV':'local','APP_DEBUG':'false','APP_KEY':'base64:' + __import__('base64').b64encode(secrets.token_bytes(32)).decode(),
        'APP_URL':'http://localhost:8002','DB_CONNECTION':'mysql','DB_HOST':'db','DB_PORT':'3306','DB_DATABASE':TARGET,'DB_USERNAME':'bancay_p4_stage','DB_PASSWORD':writer_password,
        'MAIL_MAILER':'log','MAIL_FROM_ADDRESS':'stage@example.test','CACHE_DRIVER':'file','SESSION_DRIVER':'file','SESSION_COOKIE':'bancay_phase4_session','QUEUE_CONNECTION':'sync',
        'BANCAY_BOOTSTRAP_PASSWORD':bootstrap,'LEGACY_MIGRATION_ENABLED':'true','LEGACY_TARGET_DATABASE':TARGET,'LEGACY_DB_HOST':'db','LEGACY_DB_DATABASE':SOURCE,
        'LEGACY_DB_USERNAME':'bancay_p4_reader','LEGACY_DB_PASSWORD':reader_password,'LEGACY_MEDIA_ROOT':'/legacy-source',
        'LEGACY_REPORT_DIRECTORY':'/var/www/html/legacy-private/phase4/reports','LEGACY_BACKUP_MANIFEST':'/var/www/html/legacy-private/phase4/backup-proof.json', 'STOREFRONT_INDEXABLE':'false'}
    envfile = PRIVATE / 'staging.env'
    envfile.write_text('\n'.join(k+'='+v for k,v in env.items())+'\n')
    os.chmod(envfile, 0o600)
    state = {'container':CONTAINER,'source_database':SOURCE,'target_database':TARGET,'source_account':'SELECT only','target_account':'owned staging only', 'url':'http://localhost:8002', 'source_counts':expected}
    (PRIVATE / 'environment.json').write_text(json.dumps(state,indent=2)+'\n')
    document = extraction / 'domains/bancay.vn/public_html'
    subprocess.run(['docker','run','-d','--name',CONTAINER,'--network','bancay_default','--user','1000:1000','--env-file',str(envfile),'-p','127.0.0.1:8002:8000',
        '-v',str(ROOT)+':/var/www/html','-v',str(storage)+':/var/www/html/storage','-v',str(document)+':/legacy-source:ro','-v',str(ROOT/'backups')+':/var/www/html/backups:ro',
        '-w','/var/www/html','bancay-php:8.3','php','artisan','serve','--host=0.0.0.0','--port=8000','--no-reload'],cwd=ROOT,check=True,stdout=subprocess.PIPE)
    app(['php','artisan','migrate','--no-interaction'])
    app(['php','artisan','db:seed','--class=Exceedone\\Exment\\Database\\Seeder\\InstallSeeder','--force','--no-interaction'])
    app(['php','artisan','store:initialize-local-admin','--no-interaction'])
    app(['php','artisan','store:configure-admin','--no-interaction'])
    target_backup = PRIVATE / 'target-before.sql'
    with target_backup.open('xb') as file:
        os.chmod(target_backup,0o600)
        db(['mariadb-dump','--user=root','--single-transaction','--quick','--skip-comments','--skip-dump-date',TARGET],stdout=file,stderr=subprocess.PIPE)
    tables = query(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='{TARGET}' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;").splitlines()
    before = snapshot(TARGET,tables)
    scratch='bancay_phase4_backup_verify'
    assert not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{scratch}';")
    query(f'CREATE DATABASE `{scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    try:
        with target_backup.open('rb') as file:
            db(['mariadb','--user=root',scratch],stdin=file,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
        assert snapshot(scratch,tables)==before
    finally:
        query(f'DROP DATABASE `{scratch}`;')
    container_path=lambda p:'/var/www/html/'+str(p.relative_to(ROOT))
    proof={'status':'BACKUP VERIFIED','target_database':TARGET,'source_sql':{'path':container_path(sql),'sha256':hash_file(sql)},'source_zip':{'path':container_path(original/'bancay.zip'),'sha256':hash_file(original/'bancay.zip')},'target_backup':{'path':container_path(target_backup),'sha256':hash_file(target_backup)},'extracted_files_verified':len(manifest['extracted_sha256_manifest']), 'archive_crc':'PASS', 'source_restore_counts':expected,'target_restore_tables':len(tables),'target_restore':'PASS'}
    (PRIVATE/'backup-proof.json').write_text(json.dumps(proof,indent=2)+'\n')
    os.chmod(PRIVATE/'backup-proof.json',0o600)
    safe={k:v for k,v in proof.items() if k not in ['source_sql','source_zip','target_backup']}
    safe['artifact_hashes']={k:proof[k]['sha256'] for k in ['source_sql','source_zip','target_backup']}
    (OUT/'backup-verification.json').write_text(json.dumps(safe,indent=2)+'\n')
    (OUT/'environment.json').write_text(json.dumps(state,indent=2)+'\n')
    print('BACKUP VERIFIED: original SQL/ZIP/3644 extracted files; source980 rows read-only snapshot; fresh target/native admin; target baseline restore PASS; localhost8002.')


if __name__=='__main__':main()
