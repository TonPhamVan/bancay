<?php

use App\Admin\StoreResources;
use App\Legacy\TargetGuard;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Storefront\Catalog;
use App\Support\IntegerDisplay;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = DB::connection()->getDatabaseName();
if (! app()->environment('local') || DB::connection()->getConfig('host') !== 'db'
    || ! in_array($database, ['bancay', 'bancay_phase4_staging'], true)) {
    throw new RuntimeException('Owned local application databases only');
}
DB::statement('SET SESSION TRANSACTION READ ONLY');
$mode = $database === 'bancay' ? (Product::whereNotNull('legacy_id')->exists() ? 'combined' : 'demo') : 'migrated';
$data = ['database' => $database, 'base' => $mode !== 'migrated' ? 'http://localhost:8000' : 'http://localhost:8002',
    'fingerprint_before' => app(TargetGuard::class)->fingerprint(), 'entities' => []];
foreach (['categories' => 'catalog', 'products' => 'catalog', 'article-categories' => 'content', 'articles' => 'content', 'banners' => 'content'] as $entity => $module) {
    $definition = StoreResources::get($module, $entity);
    $records = $definition['model']::orderBy('id')->get();
    $rows = [];
    foreach ($records as $record) {
        $row = ['id' => $record->id, 'name' => $record->{$definition['display']}, 'status' => $record->status->value, 'fields' => []];
        foreach ($definition['fields'] as $field => [$label, $type]) {
            if ($type === 'file') {
                continue;
            }
            $value = $record->$field;
            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }
            if ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d\TH:i');
            }
            $value ??= in_array($field, ['sort_order', 'priority']) ? 0 : '';
            $row['fields'][$field] = $type === 'checkbox'
                ? ['checkbox' => (bool) $value]
                : ['sha256' => hash('sha256', str_replace(["\r\n", "\r"], "\n", (string) $value))];
        }
        if ($entity === 'products') {
            $record->load(['category', 'images', 'options.values', 'variants.inventory', 'variants.optionValues']);
            $prices = $record->variants->map(fn ($v) => $v->effectivePrice())->filter(fn ($p) => $p !== null);
            $row += ['slug' => $record->slug, 'category' => $record->category->name, 'variant_count' => $record->variants->count(),
                'price_range' => $prices->isEmpty() ? 'Liên hệ' : IntegerDisplay::format($prices->min()).' – '.IntegerDisplay::format($prices->max()).' VND',
                'available' => $record->variants->sum(fn ($v) => $v->inventory?->available() ?? 0),
                'image_paths' => $record->images->pluck('path')->all(), 'option_count' => $record->options->count(),
                'variants' => $record->variants->map(fn ($v) => ['id' => $v->id, 'sku' => $v->sku, 'pricing_type' => $v->pricing_type->value,
                    'price' => (string) $v->price, 'sale_price' => (string) $v->sale_price, 'status' => $v->status->value, 'image_id' => (string) $v->image_id])->all()];
        }
        if ($entity === 'articles') {
            $row += ['slug' => $record->slug, 'category' => $record->category->name];
        }
        $rows[] = $row;
    }
    $data['entities'][$entity] = ['module' => $module, 'rows' => $rows];
}
$data['inventory'] = Inventory::with('variant.product')->orderBy('id')->get()->map(fn ($i) => ['id' => $i->id,
    'sku' => $i->variant->sku, 'quantity' => $i->quantity, 'reserved' => $i->reserved_quantity, 'available' => $i->available()])->all();
$data['orders'] = Order::with('items')->orderBy('id')->get()->map(fn ($o) => ['id' => $o->id, 'number' => $o->order_number,
    'total' => IntegerDisplay::format($o->total), 'status' => $o->status->value, 'legacy' => $o->is_legacy,
    'items' => $o->items->map(fn ($i) => ['name' => $i->product_name, 'unit_price' => IntegerDisplay::format($i->unit_price),
        'quantity' => (string) $i->quantity, 'subtotal' => IntegerDisplay::format($i->subtotal)])->all()])->all();
$settings = Setting::pluck('value', 'key');
foreach (['site_name', 'contact_phone', 'email', 'address', 'facebook', 'map', 'meta_title', 'meta_description'] as $key) {
    $data['settings'][$key] = hash('sha256', str_replace(["\r\n", "\r"], "\n", (string) ($settings[$key] ?? '')));
}
$data['public_products'] = app(Catalog::class)->products()->count();
$data['public_articles'] = app(Catalog::class)->articles()->count();
$directory = base_path('legacy-private/ui-mapping');
if (! is_dir($directory)) {
    mkdir($directory, 0700);
}
$file = $directory.'/'.$mode.'.json';
file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
chmod($file, 0600);
echo json_encode(['mode' => $mode, 'database' => $database, 'products' => count($data['entities']['products']['rows']),
    'public_products' => $data['public_products'], 'articles' => count($data['entities']['articles']['rows']), 'public_articles' => $data['public_articles']]).PHP_EOL;
