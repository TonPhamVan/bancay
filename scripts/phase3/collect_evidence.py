"""Phase 3 checks: isolated bancay_test suite, build, and read-only local business-data checks.

Run from WSL: python3 scripts/phase3/collect_evidence.py
Raw logs stay private. No production/legacy connection, import, or public deployment.
"""

import hashlib
import http.cookiejar
import json
from pathlib import Path
import re
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/phase-3/evidence'
PRIVATE = ROOT / 'legacy-private/phase3'


def write(name, value):
    (OUT / name).write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n')


def run(name, command, expected=0):
    start = time.monotonic()
    result = subprocess.run(command, cwd=ROOT, text=True, capture_output=True)
    (PRIVATE / (name + '.log')).write_text(result.stdout + result.stderr)
    record = {'name': name, 'exit_code': result.returncode, 'expected_exit_code': expected,
              'seconds': round(time.monotonic() - start, 2),
              'result': 'PASS' if result.returncode == expected else 'FAIL'}
    display = 'KNOWN FAIL — PRODUCTION BLOCKED' if name == 'composer-audit' and result.returncode == 1 else record['result']
    print(f'{name}: {display} (exit {result.returncode})', flush=True)
    return record, result.stdout


def php(source):
    result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php'], input=source,
                            cwd=ROOT, text=True, capture_output=True, check=True)
    return json.loads(result.stdout)


def versioned_sources():
    paths = subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], cwd=ROOT).decode().split('\0')
    return sorted(p for p in set(paths) if p and (p.split('/')[0] in ['app', 'config', 'database', 'routes', 'tests', 'exment-plugins', 'resources', 'docker']
        or p in ['composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js', 'compose.yaml', '.env.example', 'scripts/setup-local.sh']
        or p.startswith(('scripts/phase3/', 'public/storefront/'))))


def metadata():
    return php(r'''<?php
require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local') || DB::connection()->getDatabaseName()!=='bancay') throw new RuntimeException('Only the documented local instance is eligible.');
$old=json_decode(file_get_contents('docs/phase-1/evidence/schema.json'),true); $changes=[];
$canonical=function($rows){$values=array_map(fn($r)=>(array)$r,$rows);usort($values,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));return $values;};
foreach($old['tables'] as $table=>$expected){
 $columns=DB::select('SELECT COLUMN_NAME as column_name,COLUMN_TYPE as column_type,IS_NULLABLE as is_nullable,COLUMN_DEFAULT as column_default,EXTRA as extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$table]);
 if($canonical($columns)!=$canonical($expected['columns']))$changes[]=$table.':columns';
 $foreign=DB::select('SELECT k.COLUMN_NAME as column_name,k.REFERENCED_TABLE_NAME as referenced_table_name,k.REFERENCED_COLUMN_NAME as referenced_column_name,r.DELETE_RULE as delete_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME=r.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$table]);
 if($canonical($foreign)!=$canonical($expected['foreign_keys']))$changes[]=$table.':foreign_keys';
 $indexes=DB::select('SELECT INDEX_NAME as key_name,NON_UNIQUE as non_unique,SEQ_IN_INDEX as seq_in_index,COLUMN_NAME as column_name,INDEX_TYPE as index_type FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
 $keys=['key_name','non_unique','seq_in_index','column_name','index_type'];
 $expectedIndexes=array_map(function($row)use($keys){$r=[];foreach($keys as $key)$r[$key]=$row[$key];return $r;},$expected['indexes']);
 if($canonical($indexes)!=$canonical($expectedIndexes))$changes[]=$table.':indexes';
}
$orders=App\Models\Order::whereIn('customer_name',['Phase 3 Desktop Synthetic','Phase 3 Mobile Synthetic'])->with('items')->get()->map(fn($o)=>[
 'id'=>$o->id,'order_number'=>$o->order_number,'status'=>$o->status->value,'total'=>$o->total,
 'items'=>$o->items->map(fn($i)=>['sku'=>$i->sku,'quantity'=>$i->quantity,'unit_price'=>$i->unit_price,'subtotal'=>$i->subtotal])->all()])->all();
$routes=collect(app('router')->getRoutes())->filter(fn($r)=>str_starts_with($r->getName()??'','store.')||$r->getName()==='health')->map(fn($r)=>[
 'method'=>implode('|',$r->methods()),'uri'=>$r->uri(),'name'=>$r->getName(),'middleware'=>$r->gatherMiddleware(),
 'session_lock_seconds'=>$r->locksFor(),'session_lock_wait_seconds'=>$r->waitsFor()])->values()->all();
echo json_encode(['schema'=>['domain_tables_checked'=>count($old['tables']),'structural_changes'=>$changes,
 'added_technical_table'=>'checkout_attempts','technical_columns'=>Schema::getColumnListing('checkout_attempts'),'database'=>'bancay'],
 'runtime'=>['php'=>PHP_VERSION,'laravel'=>app()->version(),'exment'=>Composer\InstalledVersions::getPrettyVersion('exceedone/exment'),
 'database_server'=>DB::selectOne('SELECT VERSION() as version')->version,'debug'=>config('app.debug'),'session_driver'=>config('session.driver'),
 'cache_driver'=>config('cache.default'),'indexable'=>config('storefront.indexable'),'gd_webp'=>gd_info()['WebP Support']],
 'routes'=>$routes,'synthetic_ui_orders'=>$orders,'hidden_product_slug'=>App\Models\Product::where('status','DRAFT')->value('slug')]);
''')


def http_checks(data):
    base = 'http://localhost:8000'
    guest = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    checks = []

    def check(client, label, path, expected=200, form=None):
        request = urllib.request.Request(base + path, data=None if form is None else urllib.parse.urlencode(form).encode(),
                                         headers={'Accept': 'application/json'} if form is not None else {})
        try:
            response = client.open(request)
            status = response.status
            response.read()
        except urllib.error.HTTPError as error:
            status = error.code
        checks.append({'check': label, 'status': status, 'expected': expected, 'result': 'PASS' if status == expected else 'FAIL'})

    for path in ['/', '/san-pham', '/danh-muc/cay-trong-nha-demo', '/tim-kiem?q=Monstera', '/san-pham/monstera-chau-dat-nung-demo', '/gio-hang', '/bai-viet', '/bai-viet/lang-nghe-chiec-la-demo', '/lien-he']:
        check(guest, path, path)
    for order in data['synthetic_ui_orders']:
        check(guest, 'fresh_guest_order_IDOR_denied', '/don-hang/' + order['order_number'], 404)
    if data['hidden_product_slug']:
        check(guest, 'unpublished_product_hidden', '/san-pham/' + data['hidden_product_slug'], 404)
    check(guest, 'cart_missing_csrf', '/gio-hang', 419, {})
    check(guest, 'checkout_missing_csrf', '/thanh-toan', 419, {})
    credentials = dict(line.split('=', 1) for line in (ROOT / 'legacy-private/phase1/admin.env').read_text().splitlines() if '=' in line)
    admin = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    html = admin.open(base + '/admin/auth/login').read().decode()
    token = re.search(r'name="_token" value="([^"]+)"', html).group(1)
    response = admin.open(base + '/admin/auth/login', urllib.parse.urlencode({'_token': token, 'username': 'local-admin', 'password': credentials['BANCAY_BOOTSTRAP_PASSWORD']}).encode())
    assert '/admin/auth/login' not in response.url, 'Original local-admin login failed'
    response.read()
    check(admin, 'native_admin_dashboard_after_frontend', '/admin/plugins/bancay_dashboard')
    check(admin, 'native_admin_products_after_frontend', '/admin/plugins/bancay_catalog/products')
    check(admin, 'native_admin_orders_after_frontend', '/admin/plugins/bancay_orders/orders')
    for order in data['synthetic_ui_orders']:
        check(admin, 'native_admin_can_read_synthetic_frontend_order', '/admin/plugins/bancay_orders/orders/' + str(order['id']))
        check(admin, 'admin_session_does_not_bypass_public_order_grant', '/don-hang/' + order['order_number'], 404)
    write('http-smoke.json', {'checks': checks, 'limits': 'Synthetic local only; no response bodies, credentials or session tokens exported.'})
    return all(c['result'] == 'PASS' for c in checks)


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    PRIVATE.mkdir(parents=True, exist_ok=True, mode=0o700)
    reuse = '--reuse-checks' in sys.argv
    records = json.loads((OUT / 'verification.json').read_text())['checks'] if reuse else []
    if reuse:
        previous = json.loads((OUT / 'source-manifest.json').read_text())
        assert {p for p in previous if not p.startswith('scripts/phase3/')} == {p for p in versioned_sources() if not p.startswith('scripts/phase3/')}, 'Added/removed tested source; rerun checks.'
        for path, digest in previous.items():
            if not path.startswith('scripts/phase3/'):
                assert hashlib.sha256((ROOT / path).read_bytes()).hexdigest() == digest, 'Changed tested source: ' + path
    app = ['docker', 'compose', 'exec', '-T', 'app']
    commands = [
        ('pint', app + ['vendor/bin/pint', '--test'], 0),
        ('php-tests', ['docker', 'compose', 'exec', '-T', '-e', 'PHASE3_COLLECT_EVIDENCE=1', 'app', 'php', 'artisan', 'test', '--log-junit', 'storage/logs/phase3-junit.xml'], 0),
        ('blade-compile', app + ['php', 'artisan', 'view:cache'], 0),
        ('composer-validate', app + ['composer', 'validate', '--strict'], 0),
        ('platform', app + ['composer', 'check-platform-reqs'], 0),
        ('exment-requirements', app + ['php', 'artisan', 'exment:checkrequire'], 0),
        ('npm-ci', ['docker', 'compose', 'run', '--rm', '--no-deps', 'assets', 'npm', 'ci', '--no-fund'], 0),
        ('js-tests', ['docker', 'compose', 'run', '--rm', '--no-deps', 'assets', 'npm', 'test'], 0),
        ('assets-build', ['docker', 'compose', 'run', '--rm', '--no-deps', 'assets', 'npm', 'run', 'build'], 0),
        ('npm-audit', ['docker', 'compose', 'run', '--rm', '--no-deps', 'assets', 'npm', 'audit', '--json'], 0),
        ('composer-audit', app + ['composer', 'audit', '--locked', '--format=json'], 1),
        ('git-whitespace', ['git', 'diff', '--check'], 0),
        ('historic-evidence-preserved', ['git', 'diff', '--exit-code', 'HEAD', '--', 'docs/master-plan.md', 'docs/phase-0.md', 'docs/phase-0', 'docs/phase-1-report.md', 'docs/phase-1', 'docs/phase-2-report.md', 'docs/phase-2'], 0),
    ]
    for name, command, expected in ([] if reuse else commands):
        record, output = run(name, command, expected)
        if name == 'composer-audit':
            record['result'] = 'KNOWN FAIL — PRODUCTION BLOCKED' if record['exit_code'] == 1 else record['result']
            write('composer-audit.json', json.loads(output))
        if name == 'npm-audit':
            write('npm-audit.json', json.loads(output))
        if name == 'js-tests':
            match = re.search(r'tests (\d+)', output)
            write('js-tests.json', {'tests': int(match.group(1)) if match else None, 'exit_code': record['exit_code'], 'runner': 'Node --test', 'result': record['result']})
        records.append(record)
    suite = ET.parse(ROOT / 'storage/logs/phase3-junit.xml').getroot().find('testsuite')
    write('tests.json', {**{k: int(suite.attrib[k]) for k in ['tests', 'assertions', 'failures', 'errors', 'skipped']},
                         'duration_seconds': float(suite.attrib['time']), 'test_cases': [case.attrib['name'] for case in suite.iter('testcase')],
                         'database': 'bancay_test', 'concurrency': 'Two pcntl_fork processes with independent DB connections; includes same-token checkout contention.'})
    data = metadata()
    write('schema-comparison.json', data['schema']); write('runtime.json', data['runtime']); write('routes.json', data['routes'])
    write('synthetic-ui-orders.json', data['synthetic_ui_orders'])
    http_pass = http_checks(data)
    source_paths = versioned_sources()
    write('source-manifest.json', {p: hashlib.sha256((ROOT / p).read_bytes()).hexdigest() for p in sorted(source_paths)})
    master_ok = hashlib.sha256((ROOT / 'docs/master-plan.md').read_bytes()).hexdigest() == 'cc7bd8e17698ac25f04ee531c346c89378ec053bd934621b57e764c3d20f4a85'
    # Collection::where/whereNull in layout DTO navigation filters are in-memory operations.
    # This static guard supplements review of the eager-loaded view inputs; it is not a PHP type proof.
    no_db_in_blade = not any(re.search(r'DB::|::query\(|::where\(|App\\Models\\', (ROOT / p).read_text()) for p in source_paths if p.endswith('.blade.php') and not p.startswith('exment-plugins/'))
    result = {'date': '2026-10-28', 'checks': records, 'master_plan_byte_preserved': master_ok,
              'public_blade_has_no_db_queries': no_db_in_blade, 'http_checks_pass': http_pass,
              'domain_schema_unchanged': not data['schema']['structural_changes'],
              'production': 'BLOCKED — Exment v6/Laravel10 dependency advisories remain',
              'limits': ['Synthetic local only', 'Chrome/Edge/device Safari matrix not connected', 'No production import/traffic/SMTP/shipping/payment test']}
    write('verification.json', result)
    okay = all(r['exit_code'] == r['expected_exit_code'] for r in records) and master_ok and no_db_in_blade and http_pass and not data['schema']['structural_changes']
    print('Local verification PASS with recorded dependency risks.' if okay else 'Verification FAILED; inspect evidence.', flush=True)
    raise SystemExit(0 if okay else 1)


if __name__ == '__main__':
    main()
