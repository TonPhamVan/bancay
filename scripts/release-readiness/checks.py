"""Phase5 final regression collector. Raw output stays ignored; tests use bancay_test only."""
import json,subprocess,xml.etree.ElementTree as ET
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];P=ROOT/'legacy-private/release-readiness';OUT=ROOT/'docs/release-readiness/evidence';checks={}
def run(name,args):
 r=subprocess.run(args,cwd=ROOT,capture_output=True,text=True);(P/(name+'.log')).write_text(r.stdout+r.stderr);checks[name]={'exit_code':r.returncode,'status':'PASS'if r.returncode==0 else'FAIL'};print(name,checks[name]['status'],flush=True);return r
run('pint-format',['docker','exec','bancay-app-1','vendor/bin/pint'])
r=run('php-tests',['docker','exec','bancay-app-1','php','artisan','test','--log-junit=legacy-private/release-readiness/junit.xml'])
x=ET.parse(P/'junit.xml').getroot();s=x.find('testsuite');result={k:int(s.get(k,'0'))for k in ['tests','assertions','errors','failures','skipped']};result['status']='PASS'if r.returncode==0 and result['tests']>=148 and not result['errors']+result['failures'] else'FAIL';result['time_seconds']=float(s.get('time','0'));result['cases']=[{'class':c.get('class'),'name':c.get('name'),'status':'FAIL'if c.find('failure')is not None or c.find('error')is not None else'SKIPPED'if c.find('skipped')is not None else'PASS'}for c in x.iter('testcase')];(OUT/'tests.json').write_text(json.dumps(result,indent=2)+'\n');print({k:v for k,v in result.items()if k!='cases'},flush=True)
for name,args in [('pint',['vendor/bin/pint','--test']),('composer-validate',['composer','validate','--strict']),('platform',['composer','check-platform-reqs']),('exment-requirements',['php','artisan','exment:checkrequire']),('composer-audit',['composer','audit','--format=json']),('blade-compile',['php','artisan','view:cache'])]:
 r=run(name,['docker','exec','bancay-app-1']+args)
 if name=='composer-audit':
  try:(OUT/'composer-audit.json').write_text(json.dumps(json.loads(r.stdout),indent=2)+'\n')
  except:checks[name]['status']='FAIL'
(OUT/'checks-php.json').write_text(json.dumps(checks,indent=2)+'\n')
if any(x['status']!='PASS'for x in checks.values()):raise SystemExit(1)
