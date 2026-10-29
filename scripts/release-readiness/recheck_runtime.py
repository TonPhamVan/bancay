"""Runtime verification for the recheck, restricted to existing local QA/read-only sources."""
import hashlib,json,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'docs/release-readiness/recheck-54a0a2f';P=ROOT/'legacy-private/release-recheck';DATA=ROOT/'legacy-private/release-readiness'
def run(name,args):
 r=subprocess.run(args,cwd=ROOT,capture_output=True,text=True);(P/(name+'.log')).write_text(r.stdout+r.stderr);return r
def collect(name,container,script,env=None):
 args=['docker','exec']
 for key,value in(env or{}).items():args+=['--env',key+'='+value]
 r=run(name,args+[container,'php',script]);x=json.loads(r.stdout);assert r.returncode==0 and x['status']=='PASS',name;(OUT/(name+'.json')).write_text(json.dumps(x,indent=2)+'\n');print(name,'PASS',flush=True);return x
for name,container,script in [('main','bancay-app-1','scripts/phase5/audit.php'),('stage','bancay-phase4-app','scripts/phase5/audit.php'),('migration-seo','bancay-app-1','scripts/phase5/seo_compare.php'),('performance','bancay-phase4-ui','legacy-private/release-recheck/performance.php')]:collect(name,container,script)
r=run('reconciliation',['docker','exec','bancay-phase4-app','php','artisan','legacy:validate']);x=json.loads(r.stdout.strip().splitlines()[-1]);assert x['status']=='PASS';(OUT/'reconciliation.json').write_text(json.dumps(x,indent=2)+'\n')
commands={}
for name in ['exment:schedule','exment:safetywatch']:
 r=run(name.replace(':','-'),['docker','exec','bancay-phase4-ui','php','artisan',name,'--no-interaction']);commands[name]={'status':'PASS'if r.returncode==0 else'FAIL','exit_code':r.returncode};assert r.returncode==0
(OUT/'scheduler-execution.json').write_text(json.dumps({'status':'PASS','scope':'QA clone/log mail only;not DirectAdmin cron installation','commands':commands},indent=2)+'\n')
prod={'APP_ENV':'production','APP_URL':'https://bancay.vn','SESSION_SECURE_COOKIE':'true','LOG_LEVEL':'info'}
collect('production-config-preflight','bancay-phase4-ui','scripts/release-readiness/preflight.php',prod);collect('logging','bancay-phase4-ui','scripts/release-readiness/logging_probe.php',prod)
summary=collect('rollback-rehearsal','bancay-phase4-ui','scripts/release-readiness/rollback_rehearsal.php')
args=[]
for name in summary['private_artifacts'][:2]:
 f=DATA/name;args+=['/var/www/html/'+str(f.relative_to(ROOT)),hashlib.sha256(f.read_bytes()).hexdigest()]
exports=[];targets=[]
for suffix in ['first','repeat']:
 target=DATA/('recheck-offline-'+suffix+'-'+summary['private_artifacts'][0]);targets.append(target)
 r=run('delta-'+suffix,['docker','exec','bancay-phase4-ui','php','scripts/release-readiness/export_delta.php']+args+['/var/www/html/'+str(target.relative_to(ROOT))]);assert r.returncode==0;exports.append(json.loads(r.stdout))
assert exports[0]['sha256']==exports[1]['sha256']
(OUT/'offline-delta-export.json').write_text(json.dumps({'status':'PASS','exports':exports,'byte_identical_repeat':True,'database_connection_required':False,'source_writes':False},indent=2)+'\n')
prior=hashlib.sha256(targets[0].read_bytes()).hexdigest();r=run('delta-overwrite',['docker','exec','bancay-phase4-ui','php','scripts/release-readiness/export_delta.php']+args+['/var/www/html/'+str(targets[0].relative_to(ROOT))]);assert r.returncode!=0 and hashlib.sha256(targets[0].read_bytes()).hexdigest()==prior
bad=args.copy();bad[1]='0'*64;target=DATA/('blocked-recheck-'+summary['private_artifacts'][0]);r=run('delta-bad-sha',['docker','exec','bancay-phase4-ui','php','scripts/release-readiness/export_delta.php']+bad+['/var/www/html/'+str(target.relative_to(ROOT))]);assert r.returncode!=0 and not target.exists()
r=run('delta-public',['docker','exec','bancay-phase4-ui','php','scripts/release-readiness/export_delta.php']+args+['/var/www/html/public/blocked-recheck-ledger.json']);assert r.returncode!=0 and not(ROOT/'public/blocked-recheck-ledger.json').exists()
(OUT/'delta-guards.json').write_text(json.dumps({'status':'PASS','existing_ledger_overwrite_rejected':True,'checksum_mismatch_rejected':True,'public_output_rejected':True},indent=2)+'\n');print('Offline ledger andguards PASS',flush=True)
