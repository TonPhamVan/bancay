"""Aggregate archived access logs without publishing IPs, queries or user agents."""

import collections
import csv
import io
import json
from pathlib import Path
import re
import tarfile
from urllib.parse import unquote


def main():
    root=Path('legacy-private/phase0/extracted/domains/bancay.vn/logs')
    totals, urls, archives = collections.Counter(), collections.Counter(), []
    for path in sorted(root.iterdir()):
        if not path.is_file():continue
        member_count=0; requests=0
        with tarfile.open(path,'r:gz') as archive:
            for member in archive.getmembers():
                if not member.isfile():continue
                member_count+=1
                stream=archive.extractfile(member)
                for raw in stream:
                    line=raw.decode('utf-8',errors='replace')
                    match=re.search(r'"(GET|HEAD|POST) ([^ ]+) HTTP/[^\"]+"\s+(\d{3})',line)
                    if not match:continue
                    requests+=1;totals[match.group(3)]+=1
                    request_path=match.group(2).split('?',1)[0]
                    # Export only entity URLs made of the legacy slug character set.
                    if re.fullmatch(r'/[a-zA-Z0-9%._()~\-]+-(?:sp|dm|bv|cm)\d+\.html',request_path):
                        urls[(request_path,match.group(3))]+=1
        archives.append({'archive':path.name,'regular_members':member_count,'request_lines':requests})
    evidence=Path('docs/phase-0/evidence')
    rows=[{'old_path':key[0],'observed_status':key[1],'request_lines':value,
           'note':'ARCHIVED_LOG_OBSERVATION_NOT_CURRENT_HTTP_TEST'} for key,value in sorted(urls.items())]
    with (evidence/'seo-log-paths.csv').open('w',encoding='utf-8',newline='') as stream:
        writer=csv.DictWriter(stream,fieldnames=['old_path','observed_status','request_lines','note']);writer.writeheader();writer.writerows(rows)
    with (evidence/'seo-urls.csv').open(encoding='utf-8') as stream:
        db_paths={row['old_path'] for row in csv.DictReader(stream)}
    observed_paths={key[0] for key in urls}
    summary={'archives':archives,'request_lines_total':sum(totals.values()),'status_counts':dict(totals),
             'unique_entity_paths':len({key[0] for key in urls}),
             'paths_not_in_db_after_percent_decoding':len({unquote(p) for p in observed_paths}-db_paths),
             'percent_encoded_entity_paths':sum(unquote(p)!=p for p in observed_paths),
             'privacy':'Only safe entity paths and aggregate status counts exported; no IP, query, referrer or user agent',
             'limitations':'Backend and nginx rotated logs can overlap; counts are not unique visits; no production HTTP requests'}
    (evidence/'logs-summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps(summary,ensure_ascii=False,indent=2))


if __name__=='__main__':main()
