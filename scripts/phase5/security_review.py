"""Narrow credential scan of current tracked source and every reachable historical text blob.
Findings contain locations only, never matched credential values. Not a penetration test.
"""
import hashlib,json,re,subprocess
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'docs/phase-5/evidence'
rules={'private_key':re.compile(rb'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),'github_token':re.compile(rb'\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,})\b'),'aws_access_key':re.compile(rb'\b(?:AKIA|ASIA)[A-Z0-9]{16}\b'),'env_secret_value':re.compile(rb'(?im)^[ \t]*(?:APP_KEY|DB_PASSWORD|DB_ROOT_PASSWORD|MAIL_PASSWORD|SMTP_PASSWORD)[ \t]*=[ \t]*([^\s#]+)')}
allow={b'',b'""',b"''",b'null',b'${DB_PASSWORD}',b'${DB_PASSWORD:?Generate'}
def inspect(data,label):
 hits=[]
 for code,pattern in rules.items():
  for match in pattern.finditer(data):
   if code=='env_secret_value' and(match.group(1)in allow or match.group(1).startswith(b'${')):continue
   hits.append({'path':label,'rule':code,'line':data[:match.start()].count(b'\n')+1})
 return hits
files=subprocess.check_output(['git','ls-files','-z','--cached','--others','--exclude-standard'],cwd=ROOT).decode().split('\0');current=[];checked=0
for name in dict.fromkeys(files):
 p=ROOT/name
 if not name or not p.is_file()or p.stat().st_size>5000000:continue
 data=p.read_bytes()
 if b'\0'in data:continue
 checked+=1;current+=inspect(data,name)
historical=[];count=0
for row in subprocess.check_output(['git','rev-list','--objects','--all'],cwd=ROOT,text=True).splitlines():
 if ' 'not in row:continue
 sha,name=row.split(' ',1)
 if Path(name).suffix.lower()not in ['.php','.py','.js','.cjs','.sh','.md','.json','.yaml','.yml','.ini','.example']:continue
 data=subprocess.check_output(['git','cat-file','blob',sha],cwd=ROOT,stderr=subprocess.DEVNULL)
 if b'\0'in data or len(data)>5000000:continue
 count+=1;historical+=inspect(data,name+'@'+sha[:12])
source=[]
for root in ['app','resources/views']:
 for p in (ROOT/root).rglob('*.php'):
  for n,line in enumerate(p.read_text().splitlines(),1):
   if any(x in line for x in ['{!!','whereRaw','selectRaw','orderByRaw','DB::raw','unprepared(']):source.append({'path':str(p.relative_to(ROOT)),'line':n,'sha256':hashlib.sha256(line.encode()).hexdigest(),'kind':'raw_blade'if'{!!'in line else'raw_sql'})
result={'current_text_files':checked,'historical_text_blobs':count,'history_commits':int(subprocess.check_output(['git','rev-list','--count','--all'],cwd=ROOT,text=True)),'rules':list(rules),'current_findings':current,'historical_findings':historical,'rotation_required':bool(current or historical),'raw_output_sql_inventory':source,'status':'PASS'if not current and not historical else'REVIEW REQUIRED','limitations':'Narrow known-pattern scan, not exhaustive credential discovery. Private env/backups/logs excluded from publication and kept ignored.'}
(OUT/'source-security.json').write_text(json.dumps(result,indent=2)+'\n');print('Source scan:',result['status'],'current',checked,'history',count,'findings',current+historical)
