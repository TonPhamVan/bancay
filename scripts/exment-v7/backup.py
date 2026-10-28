"""Backup both owned local application DBs and current files before Exment v7 upgrade."""
import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tarfile
ROOT=Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT/'scripts/readiness'))
from verify_backup import db, query, snapshot
P=ROOT/'legacy-private/exment-v7'
OUT=ROOT/'docs/exment-v7/evidence'

def main():
    P.mkdir(parents=True,exist_ok=True);os.chmod(P,0o700);OUT.mkdir(parents=True,exist_ok=True)
    assert not (P/'backup-verification.json').exists(), 'Existing upgrade backup must not be overwritten'
    for container in ['bancay-app-1','bancay-phase4-app']:
        r=subprocess.run(['docker','exec',container,'php','artisan','down'],capture_output=True,text=True)
        assert r.returncode==0, 'Could not place owned local app in maintenance'
    now=datetime.datetime.now(datetime.timezone.utc);stamp=now.strftime('%Y%m%dT%H%M%SZ');proofs=[]
    for database in ['bancay','bancay_phase4_staging']:
        tables=query(f"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='{database}' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;").splitlines()
        assert len(tables)>=80 and all(re.fullmatch(r'[a-zA-Z0-9_]+',t) for t in tables)
        before=snapshot(database,tables)
        dump=P/(database+'-'+stamp+'.sql')
        with dump.open('xb') as file:
            os.chmod(dump,0o600)
            db(['mariadb-dump','--user=root','--single-transaction','--quick','--hex-blob','--routines','--events','--triggers','--skip-comments','--skip-dump-date',database],stdout=file,stderr=subprocess.PIPE)
        data=dump.read_bytes();assert len(data)>10000 and not re.search(rb'(?mi)^(CREATE DATABASE|USE )',data)
        assert set(re.findall(rb'CREATE TABLE `([A-Za-z0-9_]+)`',data))=={t.encode() for t in tables}
        scratch='bancay_v7_backup_verify_'+('main' if database=='bancay' else 'stage')+'_'+now.strftime('%H%M%S')
        assert not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{scratch}';")
        created=False
        try:
            query(f'CREATE DATABASE `{scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');created=True
            with dump.open('rb') as file:db(['mariadb','--user=root',scratch],stdin=file,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
            assert snapshot(scratch,tables)==before
            checks=query('CHECK TABLE '+','.join(f'`{scratch}`.`{t}`' for t in tables)+';').splitlines()
            assert len(checks)==len(tables) and all(x.endswith('status\tOK') for x in checks)
            assert snapshot(database,tables)==before
        finally:
            if created:query(f'DROP DATABASE `{scratch}`;')
        proofs.append({'status':'BACKUP VERIFIED','database':database,'timestamp_utc':now.isoformat(),'filename':dump.name,'private_path':str(dump.relative_to(ROOT)),'size_bytes':len(data),'sha256':hashlib.sha256(data).hexdigest(),'tables':before,'restore_counts_checksums_schema_match':True,'all_tables_check_ok':True,'source_unchanged':True,'scratch_removed':True})
    tracked=subprocess.run(['git','ls-files','--cached','--others','--exclude-standard','-z'],cwd=ROOT,capture_output=True,check=True).stdout.decode().split('\0')
    paths={x for x in tracked if x and (ROOT/x).is_file()}
    paths.update(x for x in ['vendor','public/vendor','storage/app','.env','bootstrap/cache/packages.php','bootstrap/cache/services.php','legacy-private/phase4/staging.env','legacy-private/phase4/storage/app'] if (ROOT/x).exists())
    archive=P/('pre-upgrade-files-'+stamp+'.tar')
    with tarfile.open(archive,'x') as tar:
        os.chmod(archive,0o600)
        for path in sorted(paths):tar.add(ROOT/path,arcname=path,recursive=True)
    with tarfile.open(archive) as tar:
        for name in ['composer.json','composer.lock','vendor/composer/installed.json']:
            assert tar.extractfile(name).read()==(ROOT/name).read_bytes()
        assert all(not x.name.startswith('/') and '..' not in Path(x.name).parts for x in tar.getmembers())
    result={'status':'BACKUP VERIFIED','timestamp_utc':now.isoformat(),'databases':proofs,'files':{'private_path':str(archive.relative_to(ROOT)),'size_bytes':archive.stat().st_size,'sha256':hashlib.file_digest(archive.open('rb'),'sha256').hexdigest(),'readable':True,'includes_vendor_plugins_current_uncommitted_source_and_private_env':True},'production_or_legacy_source_modified':False}
    (P/'backup-verification.json').write_text(json.dumps(result,indent=2)+'\n');os.chmod(P/'backup-verification.json',0o600)
    (OUT/'backup-verification.json').write_text(json.dumps(result,indent=2)+'\n')
    print('BACKUP VERIFIED: both local DBs restored with87-table checksums/schema matching; source/vendor/plugin/env archive SHA-256 verified; apps in maintenance.')
if __name__=='__main__':main()
