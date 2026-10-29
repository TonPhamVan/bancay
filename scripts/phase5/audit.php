<?php

use App\Models\Setting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = DB::connection()->getDatabaseName();
if (! app()->environment('local') || ! in_array($database, ['bancay', 'bancay_phase4_staging'], true)) {
    throw new RuntimeException('Read-only local audit only');
}
$tables = ['categories', 'products', 'product_images', 'product_options', 'product_option_values', 'product_variants', 'product_variant_values', 'inventories', 'customers', 'customer_addresses', 'orders', 'order_items', 'order_status_histories', 'article_categories', 'articles', 'banners', 'settings'];
$result = ['database' => $database, 'checked_at_utc' => gmdate('c'), 'counts' => [], 'checks' => [], 'media' => []];
foreach ($tables as $table) {
    $result['counts'][$table] = DB::table($table)->count();
}
// Discover every FK, including composite ownership references. Identifiers come only from schema metadata.
$fks = DB::select('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION', [$database]);
$fks = array_map(fn ($row) => (object) array_change_key_case((array) $row, CASE_UPPER), $fks);
$groups = [];
foreach ($fks as $fk) {
    if (in_array($fk->TABLE_NAME, $tables, true)) {
        $groups[$fk->TABLE_NAME.'.'.$fk->CONSTRAINT_NAME][] = $fk;
    }
}
foreach ($groups as $key => $columns) {
    $first = $columns[0];
    $join = implode(' AND ', array_map(fn ($f) => "c.`{$f->COLUMN_NAME}`=p.`{$f->REFERENCED_COLUMN_NAME}`", $columns));
    $nonnull = implode(' AND ', array_map(fn ($f) => "c.`{$f->COLUMN_NAME}` IS NOT NULL", $columns));
    $count = DB::selectOne("SELECT COUNT(*) n FROM `{$first->TABLE_NAME}` c LEFT JOIN `{$first->REFERENCED_TABLE_NAME}` p ON {$join} WHERE {$nonnull} AND p.`{$first->REFERENCED_COLUMN_NAME}` IS NULL")->n;
    $result['checks']['fk:'.$key] = (int) $count;
}
$checks = [
    'duplicate_sku' => 'SELECT COUNT(*) n FROM (SELECT sku FROM product_variants GROUP BY sku HAVING COUNT(*)>1) d',
    'invalid_inventory' => 'SELECT COUNT(*) n FROM inventories WHERE quantity<0 OR reserved_quantity<0 OR reserved_quantity>quantity',
    'variant_missing_inventory' => 'SELECT COUNT(*) n FROM product_variants v LEFT JOIN inventories i ON i.product_variant_id=v.id WHERE i.id IS NULL',
    'invalid_price' => "SELECT COUNT(*) n FROM product_variants WHERE (pricing_type='FIXED' AND (price IS NULL OR price<=0)) OR (pricing_type='CONTACT' AND (price IS NOT NULL OR sale_price IS NOT NULL)) OR sale_price<0 OR sale_price>price OR price>9223372036854775807",
    'orders_without_items' => 'SELECT COUNT(*) n FROM orders o WHERE NOT EXISTS(SELECT 1 FROM order_items i WHERE i.order_id=o.id)',
    'missing_item_snapshot' => "SELECT COUNT(*) n FROM order_items WHERE product_name IS NULL OR TRIM(product_name)='' OR unit_price<=0 OR quantity<=0 OR subtotal<>unit_price*quantity",
    'live_item_missing_sku' => "SELECT COUNT(*) n FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.is_legacy=0 AND (i.sku IS NULL OR TRIM(i.sku)='')",
    'invalid_order_status' => "SELECT COUNT(*) n FROM orders WHERE status NOT IN ('NEW','CONFIRMED','PACKING','SHIPPING','COMPLETED','CANCELLED','RETURNED')",
    'order_total_mismatch' => 'SELECT COUNT(*) n FROM orders o WHERE subtotal<>(SELECT SUM(i.subtotal) FROM order_items i WHERE i.order_id=o.id) OR total<>subtotal-discount_amount+shipping_fee',
    'orders_without_history' => 'SELECT COUNT(*) n FROM orders o WHERE NOT EXISTS(SELECT 1 FROM order_status_histories h WHERE h.order_id=o.id)',
    'live_item_missing_product' => 'SELECT COUNT(*) n FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.is_legacy=0 AND (i.product_id IS NULL OR i.product_variant_id IS NULL)',
];
foreach (['categories', 'products', 'articles', 'article_categories'] as $table) {
    $checks['duplicate_slug:'.$table] = "SELECT COUNT(*) n FROM (SELECT slug FROM `$table` GROUP BY slug HAVING COUNT(*)>1) d";
}
foreach ($checks as $key => $sql) {
    $result['checks'][$key] = (int) DB::selectOne($sql)->n;
}
foreach (['categories', 'article_categories'] as $table) {
    $parents = DB::table($table)->pluck('parent_id', 'id')->all();
    $cycles = 0;
    foreach ($parents as $id => $_) {
        $seen = [];
        for ($cursor = $id; $cursor !== null; $cursor = $parents[$cursor] ?? null) {
            if (isset($seen[$cursor])) {
                $cycles++;
                break;
            }
            $seen[$cursor] = true;
        }
    }
    $result['checks']['tree_cycles:'.$table] = $cycles;
}
$result['historical_null_catalog_links'] = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('o.is_legacy', true)->whereNull('i.product_id')->count();
$result['historical_unknown_sku_preserved'] = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('o.is_legacy', true)->whereNull('i.sku')->count();
$paths = [];
$logo = Setting::where('key', 'logo')->value('value');
if ($logo) {
    $paths[$logo] = true;
}
foreach (['product_images' => 'path', 'categories' => 'image', 'article_categories' => 'image', 'articles' => 'image', 'banners' => 'image'] as $table => $field) {
    foreach (DB::table($table)->whereNotNull($field)->pluck($field) as $path) {
        if ($path !== '') {
            $paths[$path] = true;
        }
    }
}
$result['html_review'] = ['fields' => 0, 'old_domain_references' => 0, 'unsafe_tags' => 0, 'inline_events' => 0, 'iframes' => 0, 'legacy_upload_paths' => 0];
foreach (['products' => ['description', 'short_description'], 'categories' => ['description'], 'article_categories' => ['description'], 'articles' => ['content', 'excerpt']] as $table => $fields) {
    foreach (DB::table($table)->get($fields) as $row) {
        foreach ($fields as $field) {
            $html = $row->$field ?? '';
            $result['html_review']['fields']++;
            $result['html_review']['old_domain_references'] += preg_match_all('#https?://(?:www\.)?bancay\.vn#i', $html);
            $result['html_review']['unsafe_tags'] += preg_match_all('#<(script|object|embed)\b#i', $html);
            $result['html_review']['iframes'] += preg_match_all('#<iframe\b#i', $html);
            $result['html_review']['inline_events'] += preg_match_all('#\son[a-z]+\s*=#i', $html);
            $result['html_review']['legacy_upload_paths'] += preg_match_all('#(?:src|poster)=["\x27](?:https?://[^/]+)?/?upload/#i', $html);
            preg_match_all('#(?:src|poster)=["\x27]/storage/([^"\x27]+)#i', $html, $matches);
            foreach ($matches[1] as $path) {
                $paths[html_entity_decode($path)] = true;
            }
        }
    }
}
$missing = 0;
$large = 0;
$largest = 0;
foreach (array_keys($paths) as $path) {
    if (! Storage::disk('public')->exists($path)) {
        $missing++;

        continue;
    }
    $size = Storage::disk('public')->size($path);
    $largest = max($largest, $size);
    $large += $size > 1000000 ? 1 : 0;
}
$result['media'] = ['unique_references' => count($paths), 'missing' => $missing, 'over_1mb' => $large, 'largest_bytes' => $largest];
$result['status'] = array_sum($result['checks']) === 0 && $missing === 0 ? 'PASS' : 'FAIL';
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
