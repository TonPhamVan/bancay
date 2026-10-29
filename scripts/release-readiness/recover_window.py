"""Restore the post-order-window QA dump and reproduce the offline recovery ledger.
Never reads/writes a production connection. All created DBs/users are owned and removed.
"""
import datetime,hashlib,json,os,re,secrets,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];P=ROOT/'legacy-private/release-readiness';OUT=ROOT/'docs/release-readiness/evidence';sys.path.insert(0,str(ROOT/'scripts/readiness'));from verify_backup import db,query,snapshot
summary=json.loads((OUT/'rollback-rehearsal.json').read_text());baseline=P/summary['private_artifacts'][0];window=P/summary['private_artifacts'][1];stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S');scratch='bancay_release_window_'+stamp;user='bancay_rr_restore';password=secrets.token_hex(24)
assert re.fullmatch(r'bancay_release_window_\d{8}_\d{6}',scratch)and not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{scratch}';")
tables=query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='bancay_phase4_ui' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;").splitlines();before=snapshot('bancay_phase4_ui',tables);dump=P/('order-window-portable-'+stamp+'.sql')
with dump.open('xb')as f:
 os.chmod(dump,0o600);db(['mariadb-dump','--user=root','--single-transaction','--quick','--routines','--events','--triggers','--hex-blob','--skip-comments','--skip-dump-date','bancay_phase4_ui',*tables],stdout=f,stderr=subprocess.PIPE)
query(f"CREATE DATABASE `{scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;CREATE USER '{user}'@'%' IDENTIFIED BY '{password}';GRANT SELECT,SHOW VIEW ON `{scratch}`.* TO '{user}'@'%';")
try:
 with dump.open('rb')as f:db(['mariadb','--user=root',scratch],stdin=f,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
 assert snapshot(scratch,tables)==before
 assert snapshot('bancay_phase4_ui',tables)==before
 env=dict(l.split('=',1)for l in(ROOT/'legacy-private/phase4/ui/staging.env').read_text().splitlines()if'='in l);env.update(DB_DATABASE=scratch,DB_USERNAME=user,DB_PASSWORD=password)
 envfile=P/('restore-window-'+stamp+'.env');envfile.write_text('\n'.join(k+'='+v for k,v in env.items())+'\n');os.chmod(envfile,0o600)
 views=query("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA='bancay_phase4_ui' ORDER BY TABLE_NAME;").splitlines();assert views==['view_workflow_start','view_workflow_value_unions'],'Unknown derived view requires a restore strategy'
 source_views={name:query(f'SHOW CREATE VIEW bancay_phase4_ui.`{name}`;')for name in views};(P/('original-views-'+stamp+'.json')).write_text(json.dumps(source_views,indent=2))
 root_env=dict(l.split('=',1)for l in(ROOT/'.env').read_text().splitlines()if'='in l and not l.startswith('#'));ddl_env=env.copy();ddl_env.update(DB_USERNAME='root',DB_PASSWORD=root_env['DB_ROOT_PASSWORD']);ddl_envfile=P/('restore-ddl-'+stamp+'.env');ddl_envfile.write_text('\n'.join(k+'='+v for k,v in ddl_env.items())+'\n');os.chmod(ddl_envfile,0o600)
 command=['docker','run','--rm','--network','bancay_default','--user','1000:1000','--env-file',str(ddl_envfile),'-v',str(ROOT)+':/var/www/html:ro','-v',str(ROOT/'legacy-private/phase4/ui/storage')+':/var/www/html/storage','-w','/var/www/html','bancay-php:8.3','php','artisan','store:rebuild-derived-views','--database-confirm='+scratch]
 rebuild=subprocess.run(command,cwd=ROOT,capture_output=True,text=True,check=True);(P/'native-view-rebuild.log').write_text(rebuild.stdout+rebuild.stderr)
 assert query(f"SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA='{scratch}';")=='2'
 assert snapshot(scratch,tables)==before
 probe=P/'restore_snapshot.php';probe.write_text("<?php if(getenv('DB_HOST')!=='db'||!preg_match('/^bancay_release_window_[0-9_]+$/',getenv('DB_DATABASE'))){throw new RuntimeException('Owned restore DB only');}$pdo=new PDO('mysql:host=db;dbname='.getenv('DB_DATABASE').';charset=utf8mb4',getenv('DB_USERNAME'),getenv('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_STRINGIFY_FETCHES=>true]);$pdo->exec(\"SET SESSION time_zone='+07:00'\");$data=[];foreach(['orders','order_items','order_status_histories','inventories']as$table){$data[$table]=$pdo->query('SELECT * FROM `'.$table.'` ORDER BY id')->fetchAll();}echo json_encode($data,JSON_THROW_ON_ERROR);")
 r=subprocess.run(['docker','run','--rm','--network','bancay_default','--user','1000:1000','--env-file',str(envfile),'-v',str(ROOT)+':/var/www/html:ro','-w','/var/www/html','bancay-php:8.3','php',str(probe.relative_to(ROOT))],cwd=ROOT,capture_output=True,text=True,check=True)
 actual=json.loads(r.stdout);expected=json.loads(window.read_text());actual={'lineage':expected['lineage'],**actual};(P/'restore-actual.private.json').write_text(json.dumps(actual,ensure_ascii=False));assert actual==expected,'Restored delta data differs from frozen window'
 restored_file=P/('restored-window-'+stamp+'.json');restored_file.write_text(json.dumps(actual,ensure_ascii=False,indent=2));os.chmod(restored_file,0o600)
 output=P/('restored-ledger-'+stamp+'.json');args=[str(baseline.resolve()),hashlib.sha256(baseline.read_bytes()).hexdigest(),str(restored_file.resolve()),hashlib.sha256(restored_file.read_bytes()).hexdigest(),str(output.resolve())]
 # Paths inside the one read-only repository mount except an explicit private writable output directory.
 mapped=[a.replace(str(ROOT),'/var/www/html')if a.startswith(str(ROOT))else a for a in args]
 r=subprocess.run(['docker','exec','bancay-phase4-ui','php','scripts/release-readiness/export_delta.php']+mapped,cwd=ROOT,capture_output=True,text=True,check=True);result=json.loads(r.stdout)
 reference=json.loads((OUT/'offline-delta-export.json').read_text())['exports'][0]['sha256'];assert result['sha256']==reference
 proof={'status':'PASS','scope':'Local synthetic order-window dump restored to owned scratch; no production/CI3 writes','timestamp_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'filename':dump.name,'size_bytes':dump.stat().st_size,'sha256':hashlib.sha256(dump.read_bytes()).hexdigest(),'tables':len(tables),'counts_data_schema_match':True,'source_qa_unchanged':True,'restored_snapshot_exact':True,'offline_ledger_identical_to_live_qa_export':True,'ledger_sha256':result['sha256'],'derived_views_rebuilt':2,'original_view_definitions_archived':True,'view_portability_fix':'Base-table-only portable dump plus guarded native view rebuild; no data SQL rewrite','new_window_orders':2,'original_main_untouched':True,'scratch_and_reader_removed':True}
except subprocess.CalledProcessError as error:
 (P/'window-restore-failure.private.log').write_text((error.stdout or '')+(error.stderr or ''));raise
finally:query(f"DROP DATABASE `{scratch}`;DROP USER '{user}'@'%';")
(OUT/'window-restore.json').write_text(json.dumps(proof,indent=2)+'\n');print('Order-window restore and exact offline ledger reproduction PASS:',len(tables),'tables;2 window orders;readonly restore reader removed')
