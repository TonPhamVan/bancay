"""Read an already restored, isolated MariaDB snapshot; export aggregate evidence.

No production connections. Personal records and credentials stay in private memory.
The container must have network mode 'none' and carry the bancay.phase=0 label.
"""

import argparse
import collections
import json
from pathlib import Path
import re
import subprocess


def run(command, data=None):
    result = subprocess.run(command, input=data, stdout=subprocess.PIPE,
                            stderr=subprocess.PIPE, check=False)
    if result.returncode:
        raise RuntimeError(f"Local audit command failed (exit {result.returncode}); inspect privately")
    return result.stdout.decode("utf-8")


def insert_counts(sql):
    """Count VALUES tuples independently of MariaDB, respecting SQL string escapes."""
    result = collections.Counter()
    for match in re.finditer(r"INSERT\s+INTO\s+`([^`]+)`\s*\([^;]*?\)\s*VALUES\s*", sql, re.I):
        table, index = match.group(1), match.end()
        depth, quoted = 0, False
        while index < len(sql):
            char = sql[index]
            if quoted:
                if char == "\\":
                    index += 2
                    continue
                if char == "'":
                    if index + 1 < len(sql) and sql[index + 1] == "'":
                        index += 2
                        continue
                    quoted = False
            elif char == "'":
                quoted = True
            elif char == "(":
                if depth == 0:
                    result[table] += 1
                depth += 1
            elif char == ")":
                depth -= 1
            elif char == ";" and depth == 0:
                break
            index += 1
    return dict(result)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--container", default="bancay-phase0-audit-20261027")
    parser.add_argument("--database", default="legacy_audit")
    parser.add_argument("--dump", type=Path, required=True)
    args = parser.parse_args()
    if not re.fullmatch(r"[a-zA-Z0-9_]+", args.database):
        raise ValueError("Invalid isolated database name")
    metadata = json.loads(run(["docker", "inspect", args.container]))[0]
    if (metadata["HostConfig"]["NetworkMode"] != "none" or
            metadata["Config"]["Labels"].get("bancay.phase") != "0" or
            metadata["NetworkSettings"]["Ports"]):
        raise ValueError("Container isolation not verified")

    def query(sql, db=True):
        command = ["docker", "exec", "-i", args.container, "mysql", "-uroot",
                   "--batch", "--raw", "--skip-column-names", "--default-character-set=utf8mb4"]
        if db:
            command.append(args.database)
        return run(command, sql.encode("utf-8"))

    def json_rows(sql):
        return [json.loads(line) for line in query(sql).splitlines() if line]

    tables = query("SHOW TABLES").splitlines()
    if not tables or any(not re.fullmatch(r"[a-zA-Z0-9_]+", t) for t in tables):
        raise ValueError("Unexpected schema")
    declared = insert_counts(args.dump.read_text(encoding="utf-8-sig"))
    table_info, records = [], {}
    for table in tables:
        cols = json_rows(f"SELECT JSON_OBJECT('name',COLUMN_NAME,'type',COLUMN_TYPE,'nullable',IS_NULLABLE,'key',COLUMN_KEY,'extra',EXTRA,'charset',CHARACTER_SET_NAME,'collation',COLLATION_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{table}' ORDER BY ORDINAL_POSITION")
        row_count = int(query(f"SELECT COUNT(*) FROM `{table}`").strip())
        if row_count != declared.get(table, 0):
            raise ValueError(f"Restored count does not match dump: {table}")
        fields = ",".join(f"'{c['name']}',`{c['name']}`" for c in cols)
        records[table] = json_rows(f"SELECT JSON_OBJECT({fields}) FROM `{table}`")
        table_info.append({"table": table, "rows": row_count,
                           "dump_insert_rows": declared.get(table, 0), "columns": cols,
                           "status_distribution": dict(collections.Counter(str(r.get("status")) for r in records[table])) if any(c['name']=='status' for c in cols) else {}})
    products = records["tblsanpham"]
    variants, malformed, shapes, samples, alignment = [], [], collections.Counter(), [], []
    for p in products:
        try:
            v = json.loads(p["phanloai"]) if p["phanloai"] else None
        except (ValueError, TypeError):
            malformed.append(p["id"])
            continue
        if isinstance(v, dict):
            shapes[",".join(sorted(v))] += 1
            arrays = {key: len(value) for key, value in v.items() if isinstance(value, list)}
            if isinstance(v.get("ma_pl"), list) and v["ma_pl"]:
                variants.append({"product_id": p["id"], "variant_count": len(v["ma_pl"]), "data": v})
                lengths = {key: len(v[key]) if isinstance(v.get(key), list) else None for key in ["pl1","pl2","img","price_pl","sale_pl","ma_pl","sl_pl"]}
                if len(set(lengths.values())) > 1:
                    alignment.append({"product_id": p["id"], "lengths": lengths})
                if len(samples)<4:
                    samples.append({"product_id": p["id"], "product_code": p["ma"], "title": p["title"], "variant_json": v})
        else:
            shapes[type(v).__name__] += 1
    role_rows = [{"id": r["id"], "name": r["name"], "status": r["status"]} for r in records["tblrole"]]
    def orphan(child, field, parent):
        ids = {r["id"] for r in records[parent]}
        return [r["id"] for r in records[child] if r[field] not in (None, 0, "", "0") and r[field] not in ids]
    summary = {
        "source_kind": "Restored supplied local SQL dump; production freshness unverified",
        "restore": {"network_mode": "none", "published_ports": {}, "server_version": query("SELECT VERSION()").strip(), "all_table_counts_match_dump": True, "total_rows": sum(t['rows'] for t in table_info)},
        "tables": table_info,
        "foreign_key_count": int(query("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()").strip()),
        "product_analysis": {"products": len(products), "variant_products": len(variants), "variant_rows": sum(v['variant_count'] for v in variants), "json_shapes": dict(shapes), "malformed_variant_product_ids": malformed, "misaligned_variant_product_ids": alignment, "representative_samples": samples,
            "empty_codes": sum(not str(p['ma'] or '').strip() for p in products),
            "duplicate_nonempty_codes": [{"code": code, "count": count} for code,count in collections.Counter(str(p['ma'] or '').strip() for p in products).items() if code and count>1],
            "duplicate_aliases": [{"alias": alias, "count": count} for alias,count in collections.Counter(p['alias'] for p in products).items() if count>1],
            "vip_distribution": dict(collections.Counter(str(p['vip']) for p in products)),
            "negative_stock_product_ids": [p['id'] for p in products if p['sl']<0],
            "sale_over_regular_product_ids": [p['id'] for p in products if p['giaban']>p['giale']],
            "zero_sale_products": sum(p['giaban']==0 for p in products),
            "zero_regular_products": sum(p['giale']==0 for p in products),
            "legacy_stock_total":sum(p['sl'] for p in products)},
        "order_analysis": {"count":len(records['tblorder']),"status_distribution":dict(collections.Counter(str(o['status']) for o in records['tblorder'])),"with_variant_text":sum(bool(o['phanloai']) for o in records['tblorder']),"zero_unit_price":sum(o['giaban']==0 for o in records['tblorder']),"nonpositive_quantity":sum(o['soluong']<=0 for o in records['tblorder'])},
        "admin_analysis": {"count":len(records['tbladmin']),"hash_format_distribution":dict(collections.Counter('32-hex (MD5-shaped)' if re.fullmatch('[0-9a-fA-F]{32}',r['pass']) else 'other' for r in records['tbladmin'])),"role_distribution":dict(collections.Counter(str(r['role']) for r in records['tbladmin'])),"roles":role_rows,"warehouse_values_count":len({r['kho'] for r in records['tbladmin']})},
        "orphans": {"product_category":orphan('tblsanpham','cid','tbldanhmuc'),"article_category":orphan('tblbaiviet','cid','tblchuyenmuc'),"category_parent":orphan('tbldanhmuc','parent','tbldanhmuc'),"article_category_parent":orphan('tblchuyenmuc','parent','tblchuyenmuc'),"order_product":orphan('tblorder','product_id','tblsanpham'),"admin_role":orphan('tbladmin','role','tblrole')},
        "missing_tables_referenced_by_code": [t for t in ['tblcontact','tbluser','tblraovat','tblchuyenmucsp'] if t not in tables]
    }
    evidence = Path('docs/phase-0/evidence');evidence.mkdir(parents=True,exist_ok=True)
    (evidence/'database-summary.json').write_text(json.dumps(summary,ensure_ascii=False,indent=2)+'\n')
    # Complete records stay private, including customer/contact data and password hashes.
    private = Path('legacy-private/phase0/records.private.json')
    private.write_text(json.dumps(records,ensure_ascii=False)+'\n');private.chmod(0o600)
    schema = run(['docker','exec',args.container,'mysqldump','-uroot','--no-data','--skip-comments','--skip-add-drop-table',args.database])
    if re.search(r'\bINSERT\s+INTO\b',schema,re.I):
        raise ValueError('Unexpected records in schema-only export')
    (evidence/'legacy-schema.sql').write_text(schema)
    print(json.dumps({"restore":summary['restore'],"tables":[{'table':t['table'],'rows':t['rows']} for t in table_info],"product_analysis":summary['product_analysis'],"order_analysis":summary['order_analysis'],"admin_analysis":summary['admin_analysis'],"orphans":summary['orphans']},ensure_ascii=False,indent=2))


if __name__ == '__main__':
    main()
