"""Exact owned QA cleanup; independently prove counter-only main DDL drift.

Never restores or alters main data/counters and never rewrites the original baseline.
"""
import datetime
import hashlib
import json
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts/readiness'))
from verify_backup import query, snapshot
sys.path.insert(0, str(ROOT / 'scripts/business-review'))
from qa_environment import run, tables

OUT = ROOT / 'docs/business-ui-remediation/ui-acceptance-20261031/evidence'
TARGET = 'bancay_release_candidate_20261031_002'
NAME = 'bancay-business-ui-acceptance-20261031'
USER = 'bancay_biz_' + hashlib.sha256(b'ui-acceptance-20261031').hexdigest()[:8]
receipt = json.loads((OUT / 'qa-environment.json').read_text())
assert receipt['source'] == 'bancay' and receipt['target'] == TARGET and receipt['url'] == 'http://localhost:8004'
original = (ROOT / 'legacy-private/business-remediation/ui-acceptance-20261031/source-copy.sql').read_bytes()
assert hashlib.sha256(original).hexdigest() == receipt['dump_sha256'] and len(original) == receipt['dump_bytes']
baseline = json.loads((OUT / 'database-baseline.json').read_text())
after = {name: snapshot(name, tables(name)) for name in baseline}
changed = {name: [table for table in baseline[name] if baseline[name][table] != after[name][table]] for name in baseline}
assert set(changed['bancay']) <= {'admin_operation_log', 'product_images'}, changed
assert not changed['bancay_phase4_staging'], changed
counter = None
if 'product_images' in changed['bancay']:
    before_row, after_row = baseline['bancay']['product_images'], after['bancay']['product_images']
    assert before_row['rows'] == after_row['rows'] and before_row['checksum'] == after_row['checksum']
    source_ddl = re.search(r'CREATE TABLE `product_images` \(.*?\) ENGINE=.*?;', original.decode(), re.S).group(0)[:-1]
    # The batch client serializes newlines in SHOW CREATE TABLE as literal backslash-n.
    assert hashlib.sha256(('product_images\t' + source_ddl.replace('\n', r'\n')).encode()).hexdigest() == before_row['schema_sha256']
    actual_ddl = query('SHOW CREATE TABLE bancay.product_images').split('\t', 1)[1].replace(r'\n', '\n')
    normalize = lambda ddl: re.sub(r'AUTO_INCREMENT=\d+', 'AUTO_INCREMENT=<counter>', ddl)
    assert normalize(source_ddl) == normalize(actual_ddl), 'Structural DDL changed; refuse cleanup'
    counter = {'before': int(re.search(r'AUTO_INCREMENT=(\d+)', source_ddl)[1]),
               'after': int(re.search(r'AUTO_INCREMENT=(\d+)', actual_ddl)[1]),
               'data_rows_and_checksum_equal': True, 'normalized_schema_equal': True,
               'origin': 'not inferred', 'counter_restored': False}
run(['docker', 'rm', '-f', NAME])
query(f"DROP DATABASE `{TARGET}`; DROP USER '{USER}'@'%';")
result = {'at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
          'changed_tables': changed, 'domain_row_counts_and_checksums_unchanged': True,
          'stage_all_tables_equal': True, 'main_image_auto_increment_only': counter,
          'main_audit_log': 'activity preserved; origin not inferred', 'main_writes_performed_by_cleanup': False,
          'qa_database_removed': not bool(query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{TARGET}'")),
          'qa_account_removed': not bool(query(f"SELECT User FROM mysql.user WHERE User='{USER}' AND Host='%'")),
          'qa_container_removed': True}
(OUT / 'cleanup.json').write_text(json.dumps(result, indent=2) + '\n')
print('Owned QA removed. Main domain counts/checksums and staging preserved; counter-only drift and audit activity recorded without undo.')
