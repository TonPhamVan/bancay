"""Validate current release-review evidence. PASS means consistent evidence, never production approval."""
import datetime,hashlib,json,py_compile,re,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'docs/release-readiness/evidence';sys.path.insert(0,str(ROOT/'scripts/readiness'));from verify_backup import query,snapshot
checks=[]
def read(n):return json.loads((OUT/n).read_text())
def require(ok,name):
 checks.append({'check':name,'status':'PASS'if ok else'FAIL'})
 if not ok:raise AssertionError(name)
for name in ['main.json','stage.json','migration-seo.json','reconciliation.json','performance.json','seo.json','browser-acceptance.json','browser-data-verification.json','responsive.json','admin-frontend-e2e.json','security-http.json','rollback-rehearsal.json','offline-delta-export.json','delta-guards.json','window-restore.json','view-restore-reproduction.json','backup-matrix.json','code-checkpoint.json','production-config-preflight.json','logging.json','cron-wrapper.json','scheduler-execution.json','source-preservation.json','source-security.json']:
 require(read(name)['status']=='PASS',name)
tests=read('tests.json');require((tests['tests'],tests['assertions'],tests['failures'],tests['errors'],tests['skipped'])==(158,1054,0,0,0),'158 PHP/1054 assertions,no failures/errors/skips')
for name in ['checks-php.json','checks-assets.json']:require(all(v['status']=='PASS'for v in read(name).values()),name)
require(not read('composer-audit.json')['advisories']and not read('composer-audit.json')['abandoned'],'Composer0')
require(read('npm-audit.json')['metadata']['vulnerabilities']['total']==0,'npm0')
require(len(read('seo.json')['redirects'])==201 and len(read('seo.json')['pages'])==221,'201 old URLs/221 sitemap targets')
require(sum(len(e['cases'])for e in read('browser-acceptance.json')['environments'])==47,'47 current branded cases')
require(all(not e['console_errors']and not e['page_errors']and not e['server_errors']for e in read('browser-acceptance.json')['environments']),'browser runtime clean')
require(sum(len(e['cases'])for e in read('responsive.json')['environments'])==24,'24 responsive cases')
require(len(read('admin-frontend-e2e.json')['cases'])==13,'continuous13-step E2E')
require(len(read('security-http.json')['cases'])==5,'5 actual security HTTP cases')
backup=read('backup-verification.json');artifact=ROOT/backup['private_relative_path'];require(artifact.stat().st_size==backup['size_bytes']and hashlib.sha256(artifact.read_bytes()).hexdigest()==backup['sha256'],'fresh98-table backup artifact SHA/size')
require(snapshot('bancay',list(backup['tables']))==backup['tables'],'all98 primary base tables unchanged')
require(read('window-restore.json')['offline_ledger_identical_to_live_qa_export']and read('window-restore.json')['derived_views_rebuilt']==2,'different-DB restore and offline ledger match')
code=read('code-checkpoint.json');manifest=json.loads((ROOT/code['private_manifest']).read_text());require(all((ROOT/f).is_file()and hashlib.sha256((ROOT/f).read_bytes()).hexdigest()==sha for f,sha in manifest.items()),'sealed code/assets still match current source')
require(hashlib.sha256((ROOT/'docs/phase-5/evidence/verification.json').read_bytes()).hexdigest()==read('baseline.json')['original_phase5_verification_sha256'],'historical Phase5 verification preserved')
require(hashlib.sha256((ROOT/'docs/master-plan.md').read_bytes()).hexdigest()=='cc7bd8e17698ac25f04ee531c346c89378ec053bd934621b57e764c3d20f4a85','MasterPlan original bytes')
risks=read('risk-register.json');allowed={'RESOLVED','FALSE POSITIVE','ACCEPTED LOW RISK','DEFERRED NON-BLOCKING','BLOCKER'};require(all(r['status']in allowed for r in risks),'all56 risk statuses explicit')
blockers=read('blockers.json');require({r['id']for r in blockers if r['status']=='BLOCKER'}=={'P5-B01','P5-B02','P5-B03'},'exact3 external release blockers,no artificial waiver')
require({r['id']for r in blockers if r['status']=='RESOLVED'}=={'P6-B04','P6-B05','P6-B06'},'3 confirmed fixes resolved')
require(query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='bancay_phase4_ui' OR SCHEMA_NAME LIKE 'bancay_release_window_%' OR SCHEMA_NAME LIKE 'bancay_release_view_repro_%' OR SCHEMA_NAME LIKE 'bancay_release_verify_legacy_%';")=='0','owned QA andrestore DBs removed')
require(query("SELECT COUNT(*) FROM mysql.user WHERE User IN ('bancay_p4_ui','bancay_rr_restore');")=='0','temporary DB grants revoked')
for file in ['docs/release-readiness-report.md','docs/release-readiness/directadmin.md','docs/production-runbook.md','docs/rollback-plan.md']:
 for ref in re.findall(r'\]\(([^)]+)\)',(ROOT/file).read_text()):
  if ref.startswith(('https:','http:','#')):continue
  dest=(ROOT/file).parent/ref.split('#')[0]
  require(dest.exists()or dest.resolve()==(OUT/'verification.json').resolve(),'document link:'+file+':'+ref)
require(subprocess.run(['git','diff','--check'],cwd=ROOT,capture_output=True).returncode==0,'git whitespace')
for file in(ROOT/'scripts/release-readiness').glob('*.py'):py_compile.compile(str(file),doraise=True)
source={}
for folder in ['app','config','routes','database','resources/js','resources/css','resources/views','tests','scripts/release-readiness','deployment/directadmin']:
 for file in sorted((ROOT/folder).rglob('*')):
  if file.is_file()and file.suffix in ['.php','.py','.js','.cjs','.css','.sh','.json','.htaccess']and'__pycache__'not in str(file)and'views/vendor'not in str(file):source[str(file.relative_to(ROOT))]=hashlib.sha256(file.read_bytes()).hexdigest()
for name in ['composer.lock','package-lock.json','.env.production.example']:source[name]=hashlib.sha256((ROOT/name).read_bytes()).hexdigest()
(OUT/'source-manifest.json').write_text(json.dumps(source,indent=2)+'\n')
result={'status':'PASS','checked_at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'meaning':'Local evidence consistent;actual production gates remain blocked','checks':checks,'source_files':len(source),'phase6_status':'NOT READY','release_blockers':['P5-B01','P5-B02','P5-B03'],'no_dns_docroot_ci3_or_final_migration_actions':True}
(OUT/'verification.json').write_text(json.dumps(result,indent=2)+'\n');print('Release evidence PASS:',len(checks),'checks;',len(source),'source files;3 production blockers remain')
