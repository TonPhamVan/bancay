<?php

use App\Models\Product;
use App\Services\Storefront\Cart;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'bancay_phase4_ui') {
    throw new RuntimeException('Performance cart/checkout must use disposable QA clone');
}
$product = Product::where('legacy_id', 4)->with(['variants.inventory', 'category'])->firstOrFail();
$variant = $product->variants->first(fn ($v) => $v->effectivePrice() !== null && $v->inventory->available() > 0);
$session = app('session')->driver();
$session->start();
app(Cart::class)->set($session, $product->id, $variant->id, 1);
$session->save();
$cookieName = config('session.cookie');
$cookie = app('encrypter')->encrypt(CookieValuePrefix::create($cookieName, app('encrypter')->getKey()).$session->getId(), false);
$paths = ['Home' => '/', 'Category' => '/danh-muc/'.$product->category->slug, 'Product' => '/san-pham/'.$product->slug,
    'Search' => '/tim-kiem?q='.rawurlencode($variant->sku), 'Cart' => '/gio-hang', 'Checkout' => '/thanh-toan'];
$queries = [];
DB::listen(function (QueryExecuted $query) use (&$queries) {
    $queries[] = ['sql' => $query->sql, 'ms' => $query->time];
});
$result = ['dataset' => 'QA clone of combined catalog plus synthetic browser fixtures', 'measurement' => 'warm in-process HTTP kernel+Blade+MariaDB; excludes TCP/assets/browser; five samples per page', 'pages' => []];
foreach ($paths as $page => $path) {
    $samples = [];
    for ($i = 0; $i < 5; $i++) {
        $queries = [];
        $start = hrtime(true);
        $request = Request::create('http://localhost:8003'.$path, 'GET', [], [$cookieName => $cookie]);
        $response = app(HttpKernel::class)->handle($request);
        $elapsed = (hrtime(true) - $start) / 1000000;
        $samples[] = ['http_status' => $response->getStatusCode(), 'elapsed_ms' => round($elapsed, 2), 'queries' => count($queries), 'db_ms' => round(array_sum(array_column($queries, 'ms')), 2), 'slow_queries_over_100ms' => count(array_filter($queries, fn ($q) => $q['ms'] > 100)), 'html_bytes' => strlen($response->getContent())];
        app(HttpKernel::class)->terminate($request, $response);
    }
    $times = array_column($samples, 'elapsed_ms');
    sort($times);
    $result['pages'][$page] = ['path' => $path, 'samples' => $samples, 'median_ms' => $times[2], 'max_queries' => max(array_column($samples, 'queries')), 'last_query_shapes' => array_count_values(array_column($queries, 'sql'))];
}
$result['query_plans'] = [];
foreach (['products' => "SELECT id FROM products WHERE status='PUBLISHED' AND category_id=? ORDER BY id DESC LIMIT 24", 'variants' => 'SELECT id FROM product_variants WHERE product_id=? AND status=?', 'orders' => 'SELECT id FROM orders ORDER BY id DESC LIMIT 25', 'customer_order_count' => 'SELECT COUNT(*) FROM orders WHERE customer_id=?'] as $name => $sql) {
    $bindings = match ($name) {
        'products' => [$product->category_id], 'variants' => [$product->id, 'ACTIVE'], 'customer_order_count' => [1], default => [],
    };
    $result['query_plans'][$name] = array_map(fn ($r) => array_intersect_key((array) $r, array_flip(['table', 'type', 'possible_keys', 'key', 'rows', 'Extra'])), DB::select('EXPLAIN '.$sql, $bindings));
}
$result['status'] = collect($result['pages'])->every(fn ($p) => collect($p['samples'])->every(fn ($s) => $s['http_status'] === 200 && $s['slow_queries_over_100ms'] === 0 && $s['queries'] < 30)) ? 'PASS' : 'FAIL';
$session->getHandler()->destroy($session->getId());
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
