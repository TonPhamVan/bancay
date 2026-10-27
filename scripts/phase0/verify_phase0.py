"""Verify Phase 0 artifacts, private baseline, report links and privacy boundaries."""

import csv
import json
from pathlib import Path
import re
import stat
import subprocess
from prepare_snapshot import sha256
from create_baseline import LITERAL


def main():
    root=Path.cwd(); evidence=root/'docs/phase-0/evidence'
    (evidence/'verification.json').write_text('{"status":"IN_PROGRESS"}\n')
    passed=[]
    def check(condition,label):
        if not condition:raise AssertionError(label)
        passed.append(label)
    manifest=json.loads((root/'backups/phase-0/2026-10-27/manifest.private.json').read_text())
    source=Path('/mnt/e/Workspace/GitHub/bancay_legacy')
    for artifact in manifest['artifacts']:
        check(sha256(source/artifact['name'])==artifact['sha256'],f"Input unchanged: {artifact['name']}")
        check(sha256(root/'backups/phase-0/2026-10-27'/artifact['name'])==artifact['sha256'],f"Backup hash: {artifact['name']}")
    extraction=root/'legacy-private/phase0/extracted'
    check(all(sha256(extraction/f['path'])==f['sha256'] for f in manifest['extracted_sha256_manifest']), 'All 3644 extracted file hashes match original snapshot manifest')
    for directory in [root/'backups',root/'legacy-private',root/'legacy-private/phase0/bancay-legacy']:
        check(stat.S_IMODE(directory.stat().st_mode)==0o700,f'Private directory mode: {directory.relative_to(root)}')
    for rel in ['backups/phase-0/2026-10-27/caybonmua_db.sql','legacy-private/phase0/records.private.json']:
        check(stat.S_IMODE((root/rel).stat().st_mode)==0o600,f'Private file mode: {rel}')
    expected={'source-inventory.csv':3321,'archive-inventory.csv':3644,'file-inventory.csv':2947,'missing-file-references.csv':6,'seo-urls.csv':201,'controller-methods.csv':66}
    csv_rows={}
    for path in sorted((root/'docs/phase-0').rglob('*.csv')):
        with path.open(encoding='utf-8',newline='') as stream:
            reader=csv.DictReader(stream);rows=list(reader)
        check(all(None not in r and all(value is not None for value in r.values()) for r in rows),f'CSV fields consistent: {path.name}')
        if path.name in expected:check(len(rows)==expected[path.name],f'CSV row count: {path.name}')
        csv_rows[path.name]=rows
    check(len({r['new_path_proposal'] for r in csv_rows['seo-urls.csv']})==201,'SEO proposed targets unique')
    db=json.loads((evidence/'database-summary.json').read_text())
    check(len(db['tables'])==12 and sum(t['rows'] for t in db['tables'])==980 and db['restore']['all_table_counts_match_dump'],'12 restored table counts independently match 980 dump records')
    check(db['restore']['network_mode']=='none' and db['restore']['published_ports']=={},'Restore used isolated container without published ports')
    schema=(evidence/'legacy-schema.sql').read_text()
    check(len(re.findall(r'CREATE TABLE',schema))==12 and not re.search(r'\bINSERT\s+INTO\b',schema,re.I),'Schema-only export contains 12 DDL tables and no records')
    lint=json.loads((evidence/'php-lint.json').read_text())
    check(lint['passed_files']==362 and lint['php_files']==362 and lint['exit_code']==0,'PHP 7.3 syntax check evidence covers all 362 PHP files')
    baseline=json.loads((evidence/'baseline.json').read_text());repo=root/baseline['repository']
    def git(*args):return subprocess.check_output(['git','-C',str(repo),*args]).decode().strip()
    check(git('rev-parse','HEAD')==baseline['commit'] and git('log','-1','--format=%s')=='Legacy production baseline','Baseline commit identity and message')
    check(not git('status','--porcelain') and not git('remote'),'Baseline clean and has no remotes')
    # Compare every baseline file to original; only documented credential lines differ.
    changed={entry['file'] for entry in baseline['credential_redactions']}
    baseline_files=git('ls-files','-z').rstrip('\0').split('\0')
    check(len(baseline_files)==1470,'Baseline contains 1470 source files')
    original=extraction/'domains/bancay.vn/public_html'
    actual_changes={rel for rel in baseline_files if (repo/rel).read_bytes()!=(original/rel).read_bytes()}
    check(actual_changes==changed,'Only six documented credential files differ from original source')
    for rel in ['backups/phase-0/2026-10-27/caybonmua_db.sql','legacy-private/phase0/records.private.json','.env']:
        check(subprocess.run(['git','check-ignore','-q',rel]).returncode==0,f'Private path ignored: {rel}')
    check(subprocess.run(['git','check-ignore','-q','docs/phase-0/evidence/legacy-schema.sql']).returncode==1,'Schema-only public SQL is trackable')
    documents=[root/'README.md',root/'AGENTS.md',*sorted((root/'docs').rglob('*.md'))]
    local_links=0
    for path in documents:
        text=path.read_text(encoding='utf-8-sig')
        check('\ufffd' not in text,f'UTF-8 readable: {path.relative_to(root)}')
        for match in re.finditer(r'\[[^\]]+\]\(([^)]+)\)',text):
            target=match.group(1).split('#',1)[0]
            if target and not re.match(r'https?://',target):
                check((path.parent/target).exists(),f'Local link: {path.relative_to(root)} -> {target}')
                local_links+=1
    for filename in ['functional-inventory.csv','business-rules.csv']:
        for row in csv_rows[filename]:
            location=row['source_location']
            file,line=location.rsplit(':',1)
            check((original/file).is_file() and 0<int(line)<=len((original/file).read_text(encoding='utf-8-sig',errors='replace').splitlines()),f'Source citation: {location}')
    # Check that actual password/license literals, hashes, phone/email records are
    # absent from public deliverables. These values are never printed on failure.
    sensitive=set()
    for rel in ['application/config/database.php','ckfinder/config.php']:
        for match in LITERAL.finditer((original/rel).read_text(encoding='utf-8-sig')):
            if re.search('password|licensekey',match.group('prefix'),re.I) and len(match.group('value'))>=5:sensitive.add(match.group('value'))
    records=json.loads((root/'legacy-private/phase0/records.private.json').read_text())
    sensitive.update(r['pass'] for r in records['tbladmin'])
    for r in records['tblorder']:
        for key in ['dienthoai','email']:
            if len(str(r[key]).strip())>=8:sensitive.add(str(r[key]).strip())
    public=[root/'README.md',root/'AGENTS.md',*sorted((root/'docs').rglob('*')),*sorted((root/'scripts/phase0').glob('*.py'))]
    for path in public:
        if path.is_file():
            text=path.read_text(encoding='utf-8-sig')
            check(not any(value in text for value in sensitive),f'No known credentials/hashes/order contact values: {path.relative_to(root)}')
    result={'status':'PASS','checks_passed':len(passed),'local_document_links_checked':local_links,'csv_counts':{name:len(rows) for name,rows in csv_rows.items()},'checks':passed,'limitations':'Artifact/static verification only; no live production/browser tests; hosting backup deferred by user'}
    (evidence/'verification.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps({k:v for k,v in result.items() if k!='checks'},ensure_ascii=False,indent=2))


if __name__=='__main__':main()
