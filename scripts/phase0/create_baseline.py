"""Create a private, credential-redacted Git baseline; original ZIP is untouched.

Only environment credentials/license literals are replaced. No business refactor.
Media is backed up in the canonical ZIP and is intentionally outside this source Git.
"""

import json
from pathlib import Path
import re
import subprocess


ROOT = Path('legacy-private/phase0/extracted/domains/bancay.vn/public_html')
DEST = Path('legacy-private/phase0/bancay-legacy')
MARKER = '__REDACTED_CREDENTIAL__'
LITERAL = re.compile(r'''(?P<prefix>(?:['"](?:username|password|passwd|pass|api_?key|secret|token|encryption_key|LicenseKey)['"]\s*=>|->(?:Username|Password)\s*=|\$config\[['"](?:encryption_key|LicenseKey)['"]\]\s*=)\s*)(?P<q>['"])(?P<value>.*?)(?P=q)''', re.I)
CONNECT = re.compile(r'((?:new\s+mysqli|mysql_connect|mysqli_connect)\s*\()([^\r\n)]*)(\))', re.I)
DEFINE = re.compile(r'''(define\(\s*['"](?:GUSER|GPWD|.*(?:PASSWORD|API_KEY|SECRET))['"]\s*,\s*)(['"])(.*?)(\2)''', re.I)


def redact(text):
    def literal(match):
        if match.group('value').strip() and not match.group('value').startswith('$'):
            return match.group('prefix') + match.group('q') + MARKER + match.group('q')
        return match.group(0)
    text = LITERAL.sub(literal, text)
    text = CONNECT.sub(lambda m: m.group(1) + re.sub(r'''(['"])(?:\\.|(?!\1).)*?\1''', lambda s: s.group(1)+MARKER+s.group(1), m.group(2)) + m.group(3), text)
    text = DEFINE.sub(lambda m: m.group(1)+m.group(2)+MARKER+m.group(4),text)
    return text


def git(*args):
    result = subprocess.run(['git','-C',str(DEST),*args],stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    if result.returncode:
        raise RuntimeError('Private baseline Git command failed; inspect privately')
    return result.stdout.decode().strip()


def main():
    if (DEST/'.git').exists():
        raise ValueError('Baseline already exists; inspect rather than replacing it')
    DEST.mkdir(parents=True,mode=0o700,exist_ok=True);DEST.chmod(0o700)
    redacted, excluded, files = [], [], 0
    text_suffixes = {'.php','.js','.json','.html','.htaccess','.ini','.xml','.txt','.md'}
    for source in sorted(ROOT.rglob('*')):
        if not source.is_file():continue
        relative=source.relative_to(ROOT)
        if relative.parts[0] in ('upload','.well-known') or source.name=='.DS_Store':
            excluded.append(str(relative));continue
        original=source.read_bytes();payload=original
        if source.suffix.lower() in text_suffixes:
            try:
                text=original.decode('utf-8')
                cleaned=redact(text)
                if cleaned!=text:
                    changed=[n for n,(a,b) in enumerate(zip(text.splitlines(),cleaned.splitlines()),1) if a!=b]
                    if len(text.splitlines())!=len(cleaned.splitlines()):raise ValueError('Line numbers changed')
                    redacted.append({'file':str(relative),'lines':changed,'finding':'FOUND SECRET / CREDENTIAL'})
                    payload=cleaned.encode('utf-8')
            except UnicodeDecodeError:
                pass
        target=DEST/relative;target.parent.mkdir(parents=True,exist_ok=True)
        target.write_bytes(payload);files+=1
        if source.suffix.lower() in text_suffixes:
            try:
                if redact(payload.decode('utf-8')) != payload.decode('utf-8'):
                    raise ValueError('Redaction is not idempotent')
            except UnicodeDecodeError:pass
    git('init','--initial-branch=main')
    git('add','--force','--all','--','.')
    git('-c','user.name=Legacy Audit','-c','user.email=legacy-audit@localhost','commit','--message','Legacy production baseline')
    if git('status','--porcelain') or git('remote'):
        raise ValueError('Baseline is not clean/private')
    result={'repository':'legacy-private/phase0/bancay-legacy','commit':git('rev-parse','HEAD'),'message':git('log','-1','--format=%s'),'tracked_files':int(git('ls-files').count('\n'))+1,'snapshot_files_copied':files,'remote_count':0,'directory_mode':'0700 (WSL)','credential_redactions':redacted,'excluded_groups':['upload/ (all media retained in original ZIP)','.well-known/ (validation tokens)','.DS_Store (OS metadata)'],'excluded_files':len(excluded),'canonical_original':'backups/phase-0/2026-10-27/bancay.zip','baseline_kind':'Source snapshot with credential/license redaction only; not byte-identical to original archive; no business refactor'}
    Path('docs/phase-0/evidence/baseline.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps(result,ensure_ascii=True,indent=2))


if __name__=='__main__':main()
