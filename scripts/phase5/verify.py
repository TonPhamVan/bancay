"""Verify Phase5 evidence/provenance. PASS here does not mean production READY."""
import csv,datetime,hashlib,json,py_compile,re,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'docs/phase-5/evidence'
def read(name):return json.loads((OUT/name).read_text())
checks=[]
def require(ok,name):
 checks.append({'check':name,'status':'PASS'if ok else'FAIL'})
 if not ok:raise AssertionError(name)
require(subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()==read('baseline.json')['commit'],'baseline commit unchanged; Phase5 uncommitted')
for row in read('inputs.json'):
 require(hashlib.sha256((ROOT/row['path']).read_bytes()).hexdigest()==row['sha256'],'historical input preserved:'+row['path'])
require(hashlib.sha256((ROOT/'docs/master-plan.md').read_bytes()).hexdigest()=='cc7bd8e17698ac25f04ee531c346c89378ec053bd934621b57e764c3d20f4a85','Master Plan exact original')
for n in ['integrity-main.json','integrity-staging.json','reconciliation.json','migration-seo.json','combined-preservation.json','source-preservation.json','seo.json','performance.json','clean-install.json','browser-acceptance.json','browser-data-verification.json','admin-frontend-e2e.json','security-http.json','responsive.json','e2e-database.json','source-security.json']:
 require(read(n)['status']=='PASS',n)
t=read('tests.json');require((t['tests'],t['assertions'],t['failures'],t['errors'],t['skipped'])==(149,1033,0,0,0),'full149/1033 regression')
for n in ['checks-php.json','checks-assets.json']:require(all(v['status']=='PASS'for v in read(n).values()),n)
audit=read('composer-audit.json');require(not audit['advisories']and not audit['abandoned'],'Composer0 advisories/abandoned')
require(read('npm-audit.json')['metadata']['vulnerabilities']['total']==0,'npm0 vulnerabilities')
require(read('dependency-change.json')==[{'package':'symfony/mime','before':'v7.4.19','after':'v7.4.22'}],'single compatible patch only')
require(len(read('seo.json')['redirects'])==201 and len(read('seo.json')['pages'])==221,'201 redirects and221 sitemap targets')
require(sum(p['missing_dimensions']for p in read('seo.json')['pages'])==0,'all crawled image dimensions present')
require(sum(len(e['cases'])for e in read('responsive.json')['environments'])==24,'24 responsive cases')
b=read('browser-acceptance.json');require(sum(len(e['cases'])for e in b['environments'])==47,'47 branded browser cases')
for e in b['environments']:require(not e['console_errors']and not e['page_errors']and not e['server_errors'],'no runtime errors:'+e['name'])
require(len(read('admin-frontend-e2e.json')['cases'])==13,'13 continuous admin/frontend/admin steps')
require(len(read('security-http.json')['cases'])==5,'5 actual security HTTP cases')
backup=read('backup-verification.json');file=ROOT/backup['private_relative_path'];require(file.is_file()and file.stat().st_size==backup['size_bytes']and hashlib.sha256(file.read_bytes()).hexdigest()==backup['sha256'],'private backup artifact exists and SHA256 matches')
require(backup['status']=='BACKUP VERIFIED'and backup['restore']['source_unchanged']and backup['tables_in_dump']==98,'local98-table backup/restore verified')
clean=read('clean-install.json');require(not clean['domain_schema_differences']and clean['qa_database_removed'],'clean17 domain schemas match;owned DB removed')
risk=read('risk-register.json');blocked=[r for r in risk if r['release_blocking']];require({r.get('blocker_id',r['id'])for r in blocked}=={'P5-B01','P5-B02','P5-B03'},'exact3 production blockers recorded')
require(all(r.get('owner')and r.get('evidence')and r.get('action')and r.get('test_result')and r.get('target_phase')for r in risk if r['status']not in ['RESOLVED','ALREADY RESOLVED','FALSE POSITIVE']),'open risks have owner/action/evidence/test/target')
require('PHASE 6 STATUS: NOT READY'in(ROOT/'docs/phase-5-report.md').read_text(),'report not claiming production readiness')
profile=dict(l.split('=',1)for l in(ROOT/'.env.production.example').read_text().splitlines()if'='in l and not l.startswith('#'));require(profile['APP_ENV']=='production'and profile['APP_DEBUG']=='false'and profile['SESSION_SECURE_COOKIE']=='true'and not profile['APP_KEY']and not profile['DB_PASSWORD'],'production template secure placeholders')
changed=subprocess.check_output(['git','diff','--name-only'],cwd=ROOT,text=True).splitlines();require(not any(p.startswith(('docs/phase-0/','docs/phase-1/','docs/phase-2/','docs/phase-3/','docs/phase-4/','docs/exment-v7/','docs/ui-mapping/'))for p in changed),'historical evidence not overwritten')
r=subprocess.run(['git','diff','--check'],cwd=ROOT,capture_output=True,text=True);require(r.returncode==0,'git whitespace')
for p in(ROOT/'scripts/phase5').glob('*.py'):py_compile.compile(str(p),doraise=True)
manifest={}
for directory in ['app','config','database','routes','resources/views','resources/js','resources/css','tests','scripts/phase5']:
 for p in sorted((ROOT/directory).rglob('*')):
  if p.is_file()and p.suffix in ['.php','.js','.cjs','.css','.py']and'__pycache__'not in str(p)and'views/vendor'not in str(p):manifest[str(p.relative_to(ROOT))]=hashlib.sha256(p.read_bytes()).hexdigest()
for f in ['composer.json','composer.lock','package.json','package-lock.json','.env.example','.env.production.example','compose.yaml']:
 manifest[f]=hashlib.sha256((ROOT/f).read_bytes()).hexdigest()
(OUT/'source-manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
result={'status':'PASS','meaning':'evidence and local verification consistent; not production approval','checked_at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'checks':checks,'source_files':len(manifest),'phase6_status':'NOT READY','release_blockers':[r.get('blocker_id',r['id'])for r in blocked],'no_production_cutover':True}
(OUT/'verification.json').write_text(json.dumps(result,indent=2)+'\n');print('Phase5 evidence PASS:',len(checks),'checks;',len(manifest),'source files;PHASE6 NOT READY (3 production gates)')
