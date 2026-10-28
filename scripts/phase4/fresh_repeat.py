"""Repeat on a second clean owned target, compare semantic data, then rehearse rollback."""
import json
import os
from pathlib import Path
import secrets
import subprocess
import sys

ROOT=Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT/'scripts/readiness'))
from verify_backup import db,query,snapshot
P=ROOT/'legacy-private/phase4'
OUT=ROOT/'docs/phase-4/evidence'
TARGET='bancay_phase4_repeat'
NAME='bancay-phase4-repeat'


def cli(args,label):
    r=subprocess.run(['docker','exec',NAME,'php',*args],cwd=ROOT,capture_output=True,text=True)
    (P/('repeat-'+label+'.log')).write_text(r.stdout+r.stderr)
    assert r.returncode==0,'Repeat command failed: '+label
    return json.loads(next(l for l in reversed(r.stdout.splitlines()) if l.startswith('{'))) if label!='fingerprint' else json.loads(r.stdout)


def main():
    assert not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{TARGET}';")
    stage_fingerprint=subprocess.run(['docker','exec','bancay-phase4-app','php','scripts/phase4/semantic_fingerprint.php'],cwd=ROOT,capture_output=True,text=True,check=True)
    expected=json.loads(stage_fingerprint.stdout)
    query(f'CREATE DATABASE `{TARGET}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    password=secrets.token_hex(24)
    query(f"CREATE USER 'bancay_p4_repeat'@'%' IDENTIFIED BY '{password}'; GRANT ALL PRIVILEGES ON `{TARGET}`.* TO 'bancay_p4_repeat'@'%';")
    backup=P/'target-before.sql'
    with backup.open('rb') as file:db(['mariadb','--user=root',TARGET],stdin=file,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    baseline_tables=query(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='{TARGET}' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;").splitlines()
    baseline=snapshot(TARGET,baseline_tables)
    repeat=P/'repeat'
    repeat.mkdir(exist_ok=True)
    storage=repeat/'storage'
    for folder in ['app/public','app/plugins','framework/cache/data','framework/sessions','framework/views','logs']:(storage/folder).mkdir(parents=True,exist_ok=True)
    (repeat/'reports').mkdir(exist_ok=True)
    env=dict(line.split('=',1) for line in (P/'staging.env').read_text().splitlines() if '=' in line)
    env.update(DB_DATABASE=TARGET,DB_USERNAME='bancay_p4_repeat',DB_PASSWORD=password,LEGACY_TARGET_DATABASE=TARGET,LEGACY_REPORT_DIRECTORY='/var/www/html/legacy-private/phase4/repeat/reports',LEGACY_BACKUP_MANIFEST='/var/www/html/legacy-private/phase4/repeat/proof.json')
    file=repeat/'staging.env';file.write_text('\n'.join(k+'='+v for k,v in env.items())+'\n');os.chmod(file,0o600)
    proof=json.loads((P/'backup-proof.json').read_text());proof['target_database']=TARGET
    (repeat/'proof.json').write_text(json.dumps(proof));os.chmod(repeat/'proof.json',0o600)
    source=ROOT/'legacy-private/phase0/extracted/domains/bancay.vn/public_html'
    subprocess.run(['docker','run','-d','--name',NAME,'--network','bancay_default','--user','1000:1000','--env-file',str(file),'-v',str(ROOT)+':/var/www/html','-v',str(storage)+':/var/www/html/storage','-v',str(source)+':/legacy-source:ro','-v',str(ROOT/'backups')+':/var/www/html/backups:ro','-w','/var/www/html','bancay-php:8.3','php','artisan','serve','--host=0.0.0.0','--port=8000','--no-reload'],cwd=ROOT,check=True,stdout=subprocess.PIPE)
    result={}
    try:
        for label,args in [('dry',['artisan','legacy:migrate','--dry-run','--include-orders']),('write',['artisan','legacy:migrate','--force','--include-orders']),('validation',['artisan','legacy:validate'])]:
            result[label]=cli(args,label)
            print('fresh repeat',label,result[label].get('status'),flush=True)
        actual=cli(['scripts/phase4/semantic_fingerprint.php'],'fingerprint')
        assert actual==expected,'Fresh semantic mapping differs'
        result['semantic_match']=True
        result['semantic_tables']=actual
    finally:
        subprocess.run(['docker','rm','-f',NAME],cwd=ROOT,check=True,stdout=subprocess.PIPE)
    # Roll back only the disposable target, preserving the primary migrated staging DB.
    assert TARGET=='bancay_phase4_repeat'
    query(f'DROP DATABASE `{TARGET}`; CREATE DATABASE `{TARGET}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    with backup.open('rb') as file:db(['mariadb','--user=root',TARGET],stdin=file,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    assert snapshot(TARGET,baseline_tables)==baseline
    query(f"DROP DATABASE `{TARGET}`; DROP USER 'bancay_p4_repeat'@'%';")
    safe={'status':'PASS','fresh_source_hash':result['write']['source_fingerprint'],'semantic_match':result['semantic_match'],'semantic_tables':result['semantic_tables'],'rollback_restore':'PASS: all baseline tables/counts/data checksums/schema match','primary_staging_preserved':True,'disposable_target_removed':True}
    (OUT/'fresh-repeat-rollback.json').write_text(json.dumps(safe,indent=2)+'\n')
    print('Fresh repeat semantic match and disposable rollback PASS.',flush=True)


if __name__=='__main__':main()
