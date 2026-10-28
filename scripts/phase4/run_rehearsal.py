"""Run guarded CLI rehearsal steps and export only aggregate evidence."""
import csv
from collections import Counter
import json
from pathlib import Path
import subprocess

ROOT=Path(__file__).resolve().parents[2]
PRIVATE=ROOT/'legacy-private/phase4'
OUT=ROOT/'docs/phase-4/evidence'


def run(label,args):
    result=subprocess.run(['docker','exec','bancay-phase4-app','php','artisan',*args],cwd=ROOT,capture_output=True,text=True)
    (PRIVATE/(label+'.log')).write_text(result.stdout+result.stderr)
    if result.returncode:
        print('STEP FAILED:',label, result.stdout[:1000],flush=True)
        raise SystemExit(result.returncode)
    data=json.loads(next(line for line in reversed(result.stdout.splitlines()) if line.startswith('{')))
    safe={k:v for k,v in data.items() if k!='report_directory'}
    (OUT/(label+'.json')).write_text(json.dumps(safe,indent=2)+'\n')
    print(label,data.get('status'),data.get('counts'),flush=True)
    if 'report_directory' in data:
        folder=ROOT/data['report_directory'].removeprefix('/var/www/html/')
        rows=list(csv.DictReader((folder/'migration-errors.csv').open()))
        aggregate=[{'entity':entity,'code':code,'count':count} for (entity,code),count in sorted(Counter((r['entity'],r['code']) for r in rows).items())]
        (OUT/(label+'-quarantine.json')).write_text(json.dumps(aggregate,indent=2)+'\n')
    return data


def main():
    import sys
    if sys.argv[1:] == ['first']:
        run('dry-run',['legacy:migrate','--dry-run','--include-orders'])
        run('migration',['legacy:migrate','--force','--include-orders'])
        run('reconciliation',['legacy:validate'])
    elif sys.argv[1:]==['rerun']:
        run('rerun-dry',['legacy:migrate','--dry-run','--include-orders'])
        run('rerun',['legacy:migrate','--force','--include-orders'])
        run('rerun-reconciliation',['legacy:validate'])
    else:raise SystemExit('Use first|rerun')


if __name__=='__main__':main()
