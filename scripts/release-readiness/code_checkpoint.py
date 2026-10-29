"""Seal current code+built assets after remediation. No credentials or runtime data."""
import datetime,hashlib,json,os,subprocess,tarfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'docs/release-readiness/evidence';matrix=json.loads((OUT/'backup-matrix.json').read_text());checkpoint=ROOT/matrix['private_checkpoint_directory'];stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ');files={}
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest()
prefixes=('app/','config/','bootstrap/','database/','routes/','resources/','exment-plugins/','deployment/','public/storefront/')
required=['artisan','composer.json','composer.lock','package.json','package-lock.json','.env.production.example','public/index.php','public/.htaccess']
for name in subprocess.check_output(['git','ls-files','--cached','--others','--exclude-standard'],cwd=ROOT,text=True).splitlines():
 if name.startswith(prefixes)or name in required or(name.startswith('scripts/release-readiness/')and name.endswith('.php')):
  p=ROOT/name
  if p.is_file()and not p.is_symlink():files[name]=digest(p)
for parent in ['public/build','public/vendor','public/lib','public/font','public/images']:
 for p in(ROOT/parent).rglob('*'):
  if p.is_file()and not p.is_symlink():files[str(p.relative_to(ROOT))]=digest(p)
assert all(f in files for f in required)
assert 'app/Console/Commands/RebuildDerivedViews.php'in files and'scripts/release-readiness/preflight.php'in files
assert not any(n=='.env'or n.startswith(('legacy-private/','backups/','storage/','vendor/','node_modules/'))or(n.startswith('bootstrap/cache/')and n!='bootstrap/cache/.gitignore')for n in files)
archive=checkpoint/('code-and-built-assets-'+stamp+'.tar.gz')
with tarfile.open(archive,'x:gz')as tar:
 for name in sorted(files):tar.add(ROOT/name,arcname=name,recursive=False)
os.chmod(archive,0o600)
with tarfile.open(archive,'r:gz')as tar:
 assert set(tar.getnames())==set(files)
 for member in tar.getmembers():assert hashlib.sha256(tar.extractfile(member).read()).hexdigest()==files[member.name]
manifest=checkpoint/('code-manifest-'+stamp+'.json');manifest.write_text(json.dumps(files,indent=2));os.chmod(manifest,0o600)
result={'status':'PASS','scope':'Current dirty-worktree code+assets checkpoint,not production deployment approval','filename':archive.name,'size_bytes':archive.stat().st_size,'sha256':digest(archive),'created_at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'source_files':len(files),'required_entrypoints':required,'all_archive_member_hashes_match':True,'private_data_excluded':True,'includes_guarded_native_view_command':True,'private_manifest':str(manifest.relative_to(ROOT))}
(OUT/'code-checkpoint.json').write_text(json.dumps(result,indent=2)+'\n');print('Code/asset archive PASS:',len(files),'files,front controller andnative-view command included;all member hashes match')
