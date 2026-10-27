"""Inventory the private legacy snapshot, DB file references and old SEO paths.

Exports metadata only. A missing reference is evidence for review, never a delete list.
"""

import collections
import csv
import datetime
import hashlib
import html
import json
from pathlib import Path
import re
import unicodedata
from urllib.parse import quote, unquote, urlsplit
import xml.etree.ElementTree as ET


ROOT = Path('legacy-private/phase0/extracted/domains/bancay.vn/public_html')
OUT = Path('docs/phase-0/evidence')


def csv_write(name, rows, fields):
    with (OUT / name).open('w', encoding='utf-8', newline='') as stream:
        writer = csv.DictWriter(stream, fieldnames=fields)
        writer.writeheader(); writer.writerows(rows)


def main():
    records = json.loads(Path('legacy-private/phase0/records.private.json').read_text())
    manifest = json.loads(Path('backups/phase-0/2026-10-27/manifest.private.json').read_text())
    prefix = 'domains/bancay.vn/public_html/'
    archive_inventory = [{'path':f['path'],'bytes':f['bytes'],'sha256':f['sha256'],
                          'area':'/'.join(f['path'].split('/')[:3])}
                         for f in manifest['extracted_sha256_manifest']]
    csv_write('archive-inventory.csv',archive_inventory,['path','bytes','sha256','area'])
    inventory = [{'path': f['path'][len(prefix):], 'bytes': f['bytes'], 'sha256': f['sha256'],
                  'area': f['path'][len(prefix):].split('/')[0]}
                 for f in manifest['extracted_sha256_manifest'] if f['path'].startswith(prefix)]
    csv_write('source-inventory.csv', inventory, ['path', 'bytes', 'sha256', 'area'])
    areas = collections.defaultdict(lambda: {'files': 0, 'bytes': 0})
    for item in inventory:
        areas[item['area']]['files'] += 1; areas[item['area']]['bytes'] += item['bytes']
    refs = collections.defaultdict(set)
    external_media = collections.Counter()
    def reference(path, owner):
        if not isinstance(path,str) or not path.strip(): return
        path = html.unescape(path).replace('\\/', '/').strip()
        parts = urlsplit(path)
        if parts.scheme and parts.scheme not in ('http', 'https'): return
        if parts.netloc and parts.netloc.lower() not in ('bancay.vn','www.bancay.vn','caybonmua.vn','www.caybonmua.vn'):
            external_media[parts.netloc.lower()] += 1; return
        normalized = unquote(parts.path).lstrip('/')
        if '..' in Path(normalized).parts: return
        if normalized.startswith(('upload/','images/','ckfinder/')):
            refs[normalized].add(owner)
    for row in records['tblsanpham']:
        folder = datetime.datetime.fromtimestamp(row['created_at'], datetime.timezone(datetime.timedelta(hours=7))).strftime('%m%Y')
        for name in json.loads(row['image']) if row['image'] else []:
            reference(f'upload/sp/{folder}/{name}',f"product:{row['id']}:image")
        variants = json.loads(row['phanloai']) if row['phanloai'] else {}
        for name in variants.get('img',[]) if isinstance(variants,dict) else []:
            if name: reference(f'upload/sp/{folder}/{name}',f"product:{row['id']}:variant")
    for table,field,folder in [('tblbaiviet','image','upload/news/'),('tbldanhmuc','image','upload/category/'),('tblchuyenmuc','image','upload/'),('tblslider','image','upload/slider/'),('tblbanner','file',''),('tbladmin','image',''),('tblfooter','logo','images/')]:
        for row in records[table]:
            if row[field]:reference(folder+row[field],f"{table}:{row['id']}:{field}")
    # Parse only media attributes from HTML, never export the containing records.
    for table,rows in records.items():
        for row in rows:
            for field,value in row.items():
                if isinstance(value,str) and '<' in value:
                    for match in re.finditer(r'''(?:src|poster)\s*=\s*['"]([^'"]+)['"]''',value,re.I):
                        reference(match.group(1),f"{table}:{row.get('id','singleton')}:{field}")
    asset_rows, hashes, missing = [], collections.defaultdict(list), []
    for item in inventory:
        if item['area'] in ('upload','images','ckeditor','ckfinder'):
            asset_rows.append({**item,'database_references':len(refs.get(item['path'],set())),
                               'reference_status':'DB_REFERENCED' if item['path'] in refs else 'NO_DB_REFERENCE_REVIEW'})
            hashes[item['sha256']].append(item['path'])
    for path,owners in sorted(refs.items()):
        if not (ROOT/path).is_file():
            missing.append({'legacy_path':path,'owners':'; '.join(sorted(owners)),'status':'MISSING_IN_SUPPLIED_ARCHIVE'})
    csv_write('file-inventory.csv',asset_rows,['path','bytes','sha256','area','database_references','reference_status'])
    csv_write('missing-file-references.csv',missing,['legacy_path','owners','status'])
    duplicates = [{'sha256':key,'paths':value} for key,value in hashes.items() if len(value)>1]
    (OUT/'duplicate-files.json').write_text(json.dumps(duplicates,ensure_ascii=False,indent=2)+'\n')
    methods, table_refs = [], collections.defaultdict(set)
    for path in sorted(ROOT.rglob('*.php')):
        rel = str(path.relative_to(ROOT))
        if not rel.startswith(('application/','cron/')):continue
        text = path.read_text(encoding='utf-8-sig',errors='replace')
        for table in set(re.findall(r'\btbl[a-zA-Z0-9_]+\b|\bcron_link\b',text)):
            table_refs[table].add(rel)
        if not re.match('application/(modules/[^/]+/)?controllers/',rel):continue
        matches = list(re.finditer(r'(?:public\s+)?function\s+(\w+)\s*\(([^)]*)\)',text))
        for i,match in enumerate(matches):
            body=text[match.end():matches[i+1].start() if i+1<len(matches) else len(text)]
            methods.append({'file':rel,'line':text[:match.start()].count('\n')+1,'method':match.group(1),'parameters':match.group(2),
                            'calls_checklogin':'$this->checklogin()' in body,'calls_checkrole':'$this->checkrole()' in body,
                            'model_methods':'; '.join(sorted(set(re.findall(r'\$this->(?:admin_model|site_model)->(\w+)',body)))),
                            'tables_in_body':'; '.join(sorted(set(re.findall(r'\btbl[a-zA-Z0-9_]+\b',body)))),
                            'post_fields':'; '.join(sorted(set(re.findall(r'''(?:input->post\(|\$_POST\[)['"]([^'"]+)''',body))))})
    csv_write('controller-methods.csv',methods,['file','line','method','parameters','calls_checklogin','calls_checkrole','model_methods','tables_in_body','post_fields'])
    seo=[]
    for table,entity,suffix,newbase in [('tblsanpham','Product','sp','san-pham'),('tbldanhmuc','Category','dm','danh-muc'),('tblbaiviet','Article','bv','bai-viet'),('tblchuyenmuc','ArticleCategory','cm','chuyen-muc')]:
        for row in records[table]:
            raw=row['alias'].replace('\u0111','d').replace('\u0110','D')
            raw=unicodedata.normalize('NFKD',raw).encode('ascii','ignore').decode().lower()
            slug=re.sub(r'[^a-z0-9]+','-',raw).strip('-') or entity.lower()
            seo.append({'entity':entity,'legacy_id':row['id'],'old_path':f"/{row['alias']}-{suffix}{row['id']}.html",'new_path_proposal':f"/{newbase}/{slug}-{row['id']}",'source_status':row['status'],'redirect_301':'REQUIRED_IF_ENTITY_RETAINED','decision':'DRAFT_NOT_FINAL_CANONICAL'})
    for row in seo:
        row['old_path_encoded']=quote(row['old_path'],safe='/-._~')
    csv_write('seo-urls.csv',seo,['entity','legacy_id','old_path','old_path_encoded','new_path_proposal','source_status','redirect_301','decision'])
    sitemap=[]
    try:
        tree=ET.fromstring((ROOT/'sitemap.xml').read_bytes())
        sitemap=[n.text for n in tree.iter() if n.tag.endswith('}loc') or n.tag=='loc']
    except ET.ParseError:pass
    versions={}
    for rel,pattern in [('system/core/CodeIgniter.php',r"CI_VERSION[^\n]*?([0-9]+\.[0-9]+\.[0-9]+)"),('class.phpmailer.php',r"\$Version\s*=\s*['\"]([^'\"]+)"),('ckeditor/ckeditor.js',r'version:\s*["\']([^"\']+)'),('ckfinder/ckfinder.js',r'version\s*[:=]\s*["\']([^"\']+)'),('js/bootstrap.min.js',r'Bootstrap\s+v?([0-9.]+)'),('js/jquery-3.3.1.min.js',r'jQuery\s+v?([0-9.]+)')]:
        match=re.search(pattern,(ROOT/rel).read_text(errors='replace'))
        versions[rel]=match.group(1) if match else 'NOT_IDENTIFIED_FROM_HEADER'
    summary={'areas':dict(areas),'asset_files':len(asset_rows),'unique_database_referenced_paths':len(refs),'missing_database_referenced_paths':len(missing),'asset_files_with_database_reference':sum(a['database_references']>0 for a in asset_rows),'no_database_reference_review_count':sum(a['database_references']==0 for a in asset_rows),'duplicate_groups':len(duplicates),'duplicate_extra_files':sum(len(d['paths'])-1 for d in duplicates),'external_media_hosts':dict(external_media),'controller_methods':len(methods),'versions':versions,'table_reference_candidates':{k:sorted(v) for k,v in sorted(table_refs.items())},'sitemap_url_count':len(sitemap),'sitemap_hosts':dict(collections.Counter(urlsplit(url).netloc for url in sitemap)),'seo_urls':len(seo),'unexpected_executable_uploads':[a['path'] for a in asset_rows if a['area']=='upload' and Path(a['path']).suffix.lower() in ('.php','.phtml','.phar','.exe','.sh')]}
    summary['archive_regular_files']=len(archive_inventory)
    summary['document_root_regular_files']=len(inventory)
    summary['outside_document_root_files']=len(archive_inventory)-len(inventory)
    (OUT/'source-summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps({k:v for k,v in summary.items() if k!='table_reference_candidates'},ensure_ascii=False,indent=2))


if __name__=='__main__':main()
