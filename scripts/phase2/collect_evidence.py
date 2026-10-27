"""Read-only local evidence collection. Credentials stay in memory and private files."""

import http.cookiejar
import hashlib
import json
from pathlib import Path
import re
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET


ROOT = Path(__file__).resolve().parents[2]
EVIDENCE = ROOT / 'docs/phase-2/evidence'


def write(name, value):
    (EVIDENCE / name).write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n')


def php(source):
    result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app', 'php'], input=source,
                            text=True, capture_output=True, cwd=ROOT, check=True)
    return json.loads(result.stdout)


def main():
    EVIDENCE.mkdir(exist_ok=True, parents=True)
    suite = ET.parse(ROOT / 'storage/logs/phase2-junit.xml').getroot().find('testsuite')
    write('tests.json', {**{k: int(suite.attrib[k]) for k in ['tests', 'assertions', 'failures', 'errors', 'skipped']},
                         'duration_seconds': float(suite.attrib['time']),
                         'test_cases': [case.attrib['name'] for case in suite.iter('testcase')],
                         'database': 'bancay_test', 'engine': 'real MariaDB',
                         'concurrency': 'pcntl_fork workers with independent connections, stock/order/status/SKU contention'})
    metadata = php(r'''<?php
require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local') || DB::connection()->getDatabaseName()!=='bancay') throw new RuntimeException('Local read-only collector only.');
$old=json_decode(file_get_contents('docs/phase-1/evidence/schema.json'),true);$changes=[];
$canonical=function($rows){$values=array_map(fn($r)=>is_object($r)?(array)$r:$r,$rows);usort($values,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));return $values;};
foreach($old['tables'] as $table=>$expected){
 $columns=DB::select('SELECT COLUMN_NAME as column_name,COLUMN_TYPE as column_type,IS_NULLABLE as is_nullable,COLUMN_DEFAULT as column_default,EXTRA as extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$table]);
 if($canonical($columns)!=$canonical($expected['columns']))$changes[]=$table.':columns';
 $foreign=DB::select('SELECT k.COLUMN_NAME as column_name,k.REFERENCED_TABLE_NAME as referenced_table_name,k.REFERENCED_COLUMN_NAME as referenced_column_name,r.DELETE_RULE as delete_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON k.CONSTRAINT_SCHEMA=r.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME=r.CONSTRAINT_NAME AND k.TABLE_NAME=r.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$table]);
 if($canonical($foreign)!=$canonical($expected['foreign_keys']))$changes[]=$table.':foreign_keys';
 $indexes=DB::select('SELECT INDEX_NAME as key_name,NON_UNIQUE as non_unique,SEQ_IN_INDEX as seq_in_index,COLUMN_NAME as column_name,INDEX_TYPE as index_type FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
 $keys=['key_name','non_unique','seq_in_index','column_name','index_type'];
 $indexOld=array_map(function($row)use($keys){$r=[];foreach($keys as $key)$r[$key]=$row[$key];return $r;},$expected['indexes']);
 if($canonical($indexes)!=$canonical($indexOld))$changes[]=$table.':indexes';
}
$performance=[];
foreach(['products'=>'catalog','orders'=>'orders','customers'=>'customers'] as $entity=>$module){
 DB::enableQueryLog();DB::flushQueryLog();$start=microtime(true);
 $rows=app(App\Services\Admin\StoreQueries::class)->listing($module,$entity,[])->paginate(25);
 $performance[$entity]=['rows'=>$rows->count(),'query_count'=>count(DB::getQueryLog()),'elapsed_ms'=>round((microtime(true)-$start)*1000,2)];
 DB::disableQueryLog();
}
$details=[];
foreach([['catalog','categories',App\Models\Category::class],['catalog','products',App\Models\Product::class],['catalog','inventory',App\Models\Inventory::class],['orders','orders',App\Models\Order::class],['customers','customers',App\Models\Customer::class],['content','article-categories',App\Models\ArticleCategory::class],['content','articles',App\Models\Article::class],['content','banners',App\Models\Banner::class]] as [$module,$entity,$model]){
 if($id=$model::value('id'))$details[]='/admin/plugins/bancay_'.$module.'/'.$entity.'/'.$id;
}
$group=app('router')->getMiddlewareGroups()['admin'];
echo json_encode(['schema'=>['domain_tables_checked'=>count($old['tables']),'structural_changes'=>$changes,'database'=>'bancay','server'=>DB::selectOne('SELECT VERSION() as version')->version],
 'runtime'=>['php'=>PHP_VERSION,'gd_webp'=>gd_info()['WebP Support'],'environment'=>app()->environment(),'plugin_count'=>Exceedone\Exment\Model\Plugin::where('plugin_name','like','Bancay%')->count(),
 'typed_store_menu_count'=>Exceedone\Exment\Model\Menu::where('menu_type','plugin')->where('menu_name','like','store_bancay_%')->count(),'admin_middleware'=>$group],
 'performance'=>$performance,'detail_paths'=>$details]);
''')
    write('schema-comparison.json', metadata['schema'])
    write('runtime.json', metadata['runtime'])
    write('performance.json', {'local_read_baseline': metadata['performance'],
                               'regression': 'Automated test compares 1 and 21 products; query count stays <=8 and grows by <=1.',
                               'limits': 'Small synthetic dataset; measures query adapter only, excluding native Exment shell/dropdown queries. Not a load benchmark.'})
    # Native local-admin login verifies the original private credential still works.
    credentials = dict(line.split('=', 1) for line in (ROOT / 'legacy-private/phase1/admin.env').read_text().splitlines() if '=' in line)
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    base = 'http://localhost:8000'
    html = client.open(base + '/admin/auth/login').read().decode()
    token = re.search(r'name="_token" value="([^"]+)"', html).group(1)
    login = client.open(base + '/admin/auth/login', urllib.parse.urlencode({
        '_token': token, 'username': 'local-admin', 'password': credentials['BANCAY_BOOTSTRAP_PASSWORD']}).encode())
    results = {'native_login': {'status': login.status, 'final_path': urllib.parse.urlparse(login.url).path}, 'pages': []}
    # Exment regenerates the session/CSRF token on login; use the authenticated form token.
    html = client.open(base + '/admin/plugins/bancay_catalog/categories/create').read().decode()
    token = re.search(r'name="_token" value="([^"]+)"', html).group(1)
    for path in ['/health', '/admin/plugins/bancay_dashboard', '/admin/plugins/bancay_catalog/categories',
                 '/admin/plugins/bancay_catalog/products', '/admin/plugins/bancay_catalog/inventory',
                 '/admin/plugins/bancay_orders/orders', '/admin/plugins/bancay_customers/customers',
                 '/admin/plugins/bancay_content/article-categories', '/admin/plugins/bancay_content/articles',
                 '/admin/plugins/bancay_content/banners', '/admin/plugins/bancay_settings/settings', *metadata['detail_paths']]:
        response = client.open(base + path)
        body = response.read()
        results['pages'].append({'path': path, 'status': response.status, 'bytes': len(body)})
    for label, data, expected in [('missing_csrf', {}, 419), ('invalid_category', {'_token': token}, 422)]:
        request = urllib.request.Request(base + '/admin/plugins/bancay_catalog/categories',
                                         data=urllib.parse.urlencode(data).encode(), headers={'Accept': 'application/json'})
        try:
            response = client.open(request)
            status = response.status
        except urllib.error.HTTPError as error:
            status = error.code
        results[label] = {'status': status, 'expected': expected}
        assert status == expected, label
    write('http-smoke.json', results)
    sources = set()
    for folder in ['app', 'config', 'database', 'routes', 'tests', 'exment-plugins', 'docker']:
        sources.update(path for path in (ROOT / folder).rglob('*') if path.is_file() and path.suffix in ['.php', '.json', '.ini'])
    sources.update(ROOT / path for path in ['composer.json', 'composer.lock', 'compose.yaml', 'docker/php/Dockerfile', 'scripts/setup-local.sh'])
    write('source-manifest.json', {str(path.relative_to(ROOT)): hashlib.sha256(path.read_bytes()).hexdigest() for path in sorted(sources)})
    print('Evidence collected: schema/runtime/query counts, JUnit summary, native local-admin login/pages/CSRF/validation. No credentials or response bodies exported.')


if __name__ == '__main__':
    main()
