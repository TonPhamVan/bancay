<?php

use App\Models\Category;
use App\Models\Product;
use App\Services\Storefront\Catalog;
use Composer\InstalledVersions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'bancay') {
    throw new RuntimeException('Read-only local main review only');
}
$catalog = app(Catalog::class);
$visible = $catalog->products()->get(['products.id', 'products.name', 'products.slug', 'products.category_id', 'products.legacy_id']);
$categories = Category::withCount('products')->orderBy('id')->get(['id', 'name', 'slug', 'parent_id', 'status', 'featured', 'published_at']);
$domains = [];
foreach (['Cây nội thất' => 'nội thất', 'Phân bón' => 'phân bón', 'Đất / giá thể' => 'giá thể', 'Dụng cụ' => 'dụng cụ', 'Tưới' => 'tưới', 'Đèn' => 'đèn', 'Cảnh quan' => 'cảnh quan'] as $label => $keyword) {
    $domains[$label] = ['keyword' => $keyword, 'matching_categories' => $categories->filter(fn ($c) => mb_stripos($c->name, $keyword) !== false)->pluck('id')->values(), 'visible_name_matches' => $visible->filter(fn ($p) => mb_stripos($p->name, $keyword) !== false)->pluck('id')->values()];
}
$output = ['at_utc' => gmdate(DATE_ATOM), 'versions' => ['php' => PHP_VERSION, 'laravel' => app()->version(), 'exment' => InstalledVersions::getPrettyVersion('exceedone/exment'), 'database' => DB::selectOne('SELECT VERSION() AS v')->v],
    'counts' => ['products' => Product::count(), 'visible_products' => $visible->count(), 'visible_migrated_products' => $visible->whereNotNull('legacy_id')->count(), 'categories' => $categories->count(), 'visible_categories' => $catalog->categories()->count()],
    'categories' => $categories->toArray(), 'visible_products' => $visible->map(fn ($p) => $p->only(['id', 'name', 'slug', 'category_id', 'legacy_id']))->values()->toArray(), 'business_keyword_inventory' => $domains,
    'product_columns' => Schema::getColumnListing('products'), 'related_tables' => array_values(array_filter(Schema::getTableListing(), fn ($name) => preg_match('/consult|service|attribute|specification|option|variant/', $name)))];
echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
