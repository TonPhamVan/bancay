"""Check synthetic browser orders in the owned QA database; output aggregates only."""
import collections
import datetime
import json
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'scripts/readiness'))
from verify_backup import query

QA = 'bancay_phase4_ui'
qa_env = dict(line.split('=', 1) for line in (ROOT / 'legacy-private/phase4/ui/staging.env').read_text().splitlines() if '=' in line)
combined = qa_env.get('BANCAY_QA_DATASET') == 'combined'
STAGE = 'bancay' if combined else 'bancay_phase4_staging'
primary_live_orders = 3 if combined else 0
OUT = ROOT / 'docs/phase-5/evidence'
browser = json.loads((OUT / 'browser-acceptance.json').read_text())
assert browser['status'] == 'PASS' and len(browser['environments']) == 3
reserves = collections.Counter()
for environment in browser['environments']:
    name = environment['name']
    assert re.fullmatch(r'[a-z-]+', name)
    expected = environment['expected']
    variant = int(expected['variant_id'])
    price = int(expected['unit_price'])
    qty = int(expected['quantity'])
    assert qty == 1 and price > 0
    reserves[variant] += qty
    checks = query(f"""SELECT COUNT(*) FROM `{QA}`.orders o
        JOIN `{QA}`.order_items i ON i.order_id=o.id
        JOIN `{QA}`.product_variants v ON v.id=i.product_variant_id
        JOIN `{QA}`.products p ON p.id=v.product_id
        WHERE o.customer_name='Phase4 Synthetic {name}' AND o.is_legacy=0
        AND i.product_variant_id={variant} AND i.quantity={qty} AND i.unit_price={price}
        AND i.subtotal={qty * price} AND o.total={qty * price} AND i.sku=v.sku AND i.product_name=p.name;""")
    assert checks == '1', 'Synthetic order snapshot mismatch'
for variant, reserved in reserves.items():
    assert query(f"""SELECT COUNT(*) FROM `{QA}`.inventories q
        JOIN `{STAGE}`.inventories s ON s.product_variant_id=q.product_variant_id
        WHERE q.product_variant_id={variant} AND q.quantity=s.quantity
        AND q.reserved_quantity=s.reserved_quantity+{reserved};""") == '1'
assert query(f'SELECT COUNT(*) FROM `{QA}`.orders WHERE is_legacy=0;') == str(3 + primary_live_orders)
assert query(f'SELECT COUNT(*) FROM `{STAGE}`.orders WHERE is_legacy=0;') == str(primary_live_orders)
assert query(f'SELECT COUNT(*) FROM `{QA}`.orders WHERE is_legacy=1;') == '255'
assert query(f'SELECT COUNT(*) FROM `{STAGE}`.orders WHERE is_legacy=1;') == '255'
primary_reserved = int(query(f'SELECT COALESCE(SUM(reserved_quantity),0) FROM `{STAGE}`.inventories;'))
if combined:
    assert query(f"SELECT COUNT(*) FROM `{STAGE}`.products WHERE status='PUBLISHED';") == '120'
else:
    assert primary_reserved == 0
    assert query(f"SELECT COUNT(*) FROM `{STAGE}`.products WHERE status<>'DRAFT' OR published_at IS NOT NULL;") == '0'
result = {'status': 'PASS', 'checked_at_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
          'synthetic_orders': 3, 'snapshots_exact': True, 'physical_stock_preserved_for_checkout_variants': True,
          'reserved_delta_exact': True, 'primary_reserved_quantity': primary_reserved, 'primary_historical_orders': 255,
          'primary_live_orders': primary_live_orders, 'primary_catalog_hidden': not combined, 'dataset': 'combined' if combined else 'migrated'}
(OUT / 'browser-data-verification.json').write_text(json.dumps(result, indent=2) + '\n')
print('PASS: 3 synthetic QA snapshots and exact reservations; primary historical/demo order counts preserved; dataset:', result['dataset'])
