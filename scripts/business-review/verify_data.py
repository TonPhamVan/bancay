"""Read-only evidence for the exact UI fixture and current public content gaps."""
import datetime
import hashlib
import json
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts/readiness'))
from verify_backup import query

OUT = ROOT / 'docs/business-ui-acceptance/evidence'
QA = 'bancay_release_candidate_20261029_9001'


def records(sql, columns):
    return [dict(zip(columns, line.split('\t'))) for line in query(sql).splitlines()]


inventory = json.loads((OUT / 'inventory.json').read_text())
visible = ','.join(str(p['id']) for p in inventory['visible_products'])
missing = records(f'SELECT p.id,p.name FROM bancay.products p LEFT JOIN bancay.product_images i ON i.product_id=p.id WHERE p.id IN ({visible}) GROUP BY p.id HAVING COUNT(i.id)=0;', ['id', 'name'])
variants = records(f'SELECT v.id,v.sku,v.price,v.sale_price,v.status,i.quantity,i.reserved_quantity FROM `{QA}`.product_variants v JOIN `{QA}`.inventories i ON i.product_variant_id=v.id WHERE v.product_id=122 ORDER BY v.id;', ['id', 'sku', 'price', 'sale_price', 'status', 'quantity', 'reserved'])
order = records(f'SELECT o.id,o.status,o.total,oi.sku,oi.unit_price,oi.quantity,oi.subtotal,oi.variant_description FROM `{QA}`.orders o JOIN `{QA}`.order_items oi ON oi.order_id=o.id WHERE o.id=260;', ['id', 'status', 'total', 'sku', 'unit_price', 'quantity', 'subtotal', 'variant'])
history = records(f'SELECT from_status,to_status FROM `{QA}`.order_status_histories WHERE order_id=260 ORDER BY id;', ['from', 'to'])
assert len(variants) == 4 and all(v['status'] == 'ACTIVE' and v['quantity'] == '10' and v['reserved'] == '0' for v in variants)
assert len(order) == 1 and order[0]['total'] == '180000' and order[0]['unit_price'] == '90000' and order[0]['quantity'] == '2' and order[0]['sku'] == 'QA-BIZ-4' and order[0]['status'] == 'CANCELLED'
assert [h['to'] for h in history] == ['NEW', 'CONFIRMED', 'CANCELLED']
test_path = 'products/legacy/534b9e9fcebf75058466e00ec8cebcea61b4e45f8d555904036cea17330e6655.webp'
main_file = ROOT / 'storage/app/public' / test_path
stage_file = ROOT / 'legacy-private/phase4/storage/app/public' / test_path
media = {'path': test_path, 'main_sha256': hashlib.sha256(main_file.read_bytes()).hexdigest(), 'stage_sha256': hashlib.sha256(stage_file.read_bytes()).hexdigest(), 'visually_verified': 'TEST lettering; not a plant photograph', 'product_id': 119, 'legacy_id': 127, 'variant_id': 139}
assert media['main_sha256'] == media['stage_sha256']
result = {'at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'ui_fixture_verification': 'PASS', 'qa_product_id': 122, 'qa_category_id': 15, 'option_groups': 2, 'variants': variants, 'order': order, 'history': history, 'visible_products_without_gallery': missing, 'missing_gallery_count': len(missing), 'preexisting_legacy_variant_test_image': media, 'note': 'Missing source gallery and preexisting TEST content are publication/content review gaps; this review has not changed or deleted their records.'}
(OUT / 'data-verification.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
print('QA four combinations/order snapshots/history/stock PASS; content gaps captured, no source writes.')
