"""Verify supplied legacy backups and create/restore a new local combined checkpoint.
No production endpoint is contacted. All SQL restores use newly owned local databases.
"""
import datetime,hashlib,json,os,re,subprocess,sys,tarfile,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'docs/release-readiness/evidence';P=ROOT/'legacy-private/release-readiness';stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ');ART=P/('checkpoint-'+stamp);ART.mkdir(mode=0o700)
sys.path.insert(0,str(ROOT/'scripts/readiness'));import verify_backup as backup
backup.OUT=OUT;backup.PRIVATE=ART;backup.main()
proof=json.loads((OUT/'backup-verification.json').read_text());proof['source_environment']='Local combined demo+migrated database; not live production backup';(OUT/'backup-verification.json').write_text(json.dumps(proof,indent=2)+'\n')
def digest(p):
 h=hashlib.sha256()
 with p.open('rb')as f:
  while chunk:=f.read(1024*1024):h.update(chunk)
 return h.hexdigest()
def metadata(p):return{'filename':p.name,'size_bytes':p.stat().st_size,'sha256':digest(p),'artifact_mtime_utc':datetime.datetime.fromtimestamp(p.stat().st_mtime,datetime.timezone.utc).isoformat(),'verified_at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat()}
original=ROOT/'backups/phase-0/2026-10-27';zip_path=original/'bancay.zip';sql_path=original/'caybonmua_db.sql'
assert digest(zip_path)=='6085c236432301c537e4e1488d06022bfcaf92a42ca49d32e9b81daadd52b48a'
assert digest(sql_path)=='10d71369e8ae83826aae625b007afe75931eb8c1e22b133081ebc8d9e7879f9c'
with zipfile.ZipFile(zip_path)as z:
 assert z.testzip()is None
 entries=z.infolist();uploads=[x for x in entries if not x.is_dir()and '/public_html/upload/'in x.filename];source=[x for x in entries if not x.is_dir()and '/public_html/'in x.filename]
legacy_sql=sql_path.read_bytes();assert not re.search(rb'(?mi)^\s*(?:CREATE DATABASE|DROP DATABASE|USE |CREATE USER|GRANT |LOAD DATA)',legacy_sql)
tables=[x.decode()for x in re.findall(rb'CREATE TABLE `([a-zA-Z0-9_]+)`',legacy_sql)];assert len(tables)==12
scratch='bancay_release_verify_legacy_'+datetime.datetime.now().strftime('%Y%m%d_%H%M%S');assert re.fullmatch(r'bancay_release_verify_legacy_\d{8}_\d{6}',scratch)
assert not backup.query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{scratch}';")
source_before=backup.snapshot('bancay_phase4_source',tables)
try:
 backup.query(f'CREATE DATABASE `{scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
 with sql_path.open('rb')as f:backup.db(['mariadb','--user=root',scratch],stdin=f,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
 restored=backup.snapshot(scratch,tables)
 assert all(restored[t]['rows']==source_before[t]['rows']and restored[t]['checksum']==source_before[t]['checksum']for t in tables)
 assert backup.snapshot('bancay_phase4_source',tables)==source_before
finally:backup.query(f'DROP DATABASE `{scratch}`;')
# Copy every regular public-media file; reject symlinks and never traverse the public/storage symlink.
media_root=ROOT/'storage/app/public';media={}
for file in sorted(media_root.rglob('*')):
 assert not file.is_symlink(),'Media symlink needs explicit review'
 if file.is_file():media[str(file.relative_to(media_root))]={'bytes':file.stat().st_size,'sha256':digest(file)}
media_archive=ART/'public-media.tar.gz'
with tarfile.open(media_archive,'w:gz')as archive:
 for name in media:archive.add(media_root/name,arcname=name,recursive=False)
os.chmod(media_archive,0o600);restore=ART/'media-restore';restore.mkdir()
with tarfile.open(media_archive,'r:gz')as archive:
 assert all(m.isfile()and not Path(m.name).is_absolute()and'..'not in Path(m.name).parts for m in archive.getmembers())
 archive.extractall(restore,filter='data')
assert {str(p.relative_to(restore)):{'bytes':p.stat().st_size,'sha256':digest(p)}for p in restore.rglob('*')if p.is_file()}==media
assert all(digest(media_root/n)==v['sha256']for n,v in media.items())
# Code checkpoint excludes secrets, runtime customer files, vendor and node_modules.
code={};prefixes=('app/','bootstrap/','config/','database/','routes/','resources/','exment-plugins/','deployment/','public/storefront/')
for name in subprocess.check_output(['git','ls-files','--cached','--others','--exclude-standard'],cwd=ROOT,text=True).splitlines():
 if (name.startswith(prefixes)or name in ['artisan','composer.json','composer.lock','package.json','package-lock.json','.env.production.example'])and(ROOT/name).is_file():code[name]=digest(ROOT/name)
for parent in ['public/build','public/vendor','public/lib','public/font','public/images']:
 for file in(ROOT/parent).rglob('*'):
  if file.is_file()and not file.is_symlink():code[str(file.relative_to(ROOT))]=digest(file)
code_archive=ART/'code-and-built-assets.tar.gz'
with tarfile.open(code_archive,'w:gz')as archive:
 for name in sorted(code):archive.add(ROOT/name,arcname=name,recursive=False)
os.chmod(code_archive,0o600)
(ART/'media-manifest.private.json').write_text(json.dumps(media,indent=2));(ART/'code-manifest.json').write_text(json.dumps(code,indent=2))
result={'status':'PASS','scope':'Supplied snapshot and fresh local combined checkpoint; not final production release artifact approval','legacy_source_zip':metadata(zip_path)|{'crc_all_entries':'PASS','public_html_files':len(source)},'legacy_upload_backup':{'same_zip_sha256':digest(zip_path),'upload_files':len(uploads),'crc':'PASS'},'legacy_database_sql':metadata(sql_path)|{'restore':'PASS','tables':12,'rows':sum(r['rows']for r in restored.values()),'counts_and_data_checksums_match_source':True,'source_unchanged':True,'scratch_removed':True},'new_database':{'proof':'backup-verification.json','status':'BACKUP VERIFIED'},'new_media':metadata(media_archive)|{'restore':'PASS','files':len(media),'all_file_hashes_match':True,'source_unchanged':True},'code_and_assets':metadata(code_archive)|{'source_files':len(code)},'production_freshness':'NOT VERIFIED: no current DirectAdmin export/freeze receipt','directadmin_config_backup':'NOT PROVIDED; user confirmed no target profile','private_checkpoint_directory':str(ART.relative_to(ROOT)),'no_production_actions':True}
(OUT/'backup-matrix.json').write_text(json.dumps(result,indent=2)+'\n');print('Backup verification PASS:legacy source/upload CRC,SQL12-table restore,current98-table DB restore,media restore',len(media),'files;production freshness unverified',flush=True)
