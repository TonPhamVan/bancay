<?php

use App\Actions\Catalog\CreateVariant;
use App\Actions\Commerce\ChangeOrderStatus;
use App\Actions\Commerce\CreateOrder;
use App\Domain\Commerce\OrderStatus;
use App\Services\Admin\CatalogEditor;
use App\Services\Admin\RecordEditor;
use App\Services\Release\OrderDelta;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'bancay_phase4_ui' || config('mail.default') !== 'log') {
    throw new RuntimeException('Disposable local QA clone with log-only mail required.');
}
$directory = base_path('legacy-private/release-readiness');
$suffix = bin2hex(random_bytes(5));
$editor = app(RecordEditor::class);
$published = now()->subDay()->toDateTimeString();
$category = $editor->save('catalog', 'categories', ['name' => 'Recovery Synthetic '.$suffix, 'slug' => 'recovery-'.$suffix,
    'status' => 'PUBLISHED', 'published_at' => $published, 'sort_order' => 0, 'featured' => 0]);
$product = $editor->save('catalog', 'products', ['name' => 'Recovery Synthetic Plant', 'slug' => 'recovery-plant-'.$suffix,
    'category_id' => $category->id, 'status' => 'PUBLISHED', 'published_at' => $published, 'featured' => 0]);
$variant = app(CreateVariant::class)->execute($product, ['sku' => 'RECOVERY-'.$suffix, 'status' => 'ACTIVE', 'pricing_type' => 'FIXED', 'price' => 100000, 'quantity' => 10]);
$lineage = 'verified-qa-'.bin2hex(random_bytes(16));
$snapshot = function () use ($lineage) {
    $result = ['lineage' => $lineage];
    foreach (OrderDelta::TABLES as $table) {
        $result[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    return $result;
};
$save = function ($name, $value) use ($directory) {
    $path = $directory.'/'.$name;
    if (is_file($path)) {
        throw new RuntimeException('Refuse to overwrite recovery artifacts.');
    }
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    chmod($path, 0600);
};
$before = $snapshot();
$save('baseline-'.$suffix.'.json', $before);
$create = fn () => app(CreateOrder::class)->execute(['customer_name' => 'Recovery Synthetic Guest', 'customer_phone' => '0900000000',
    'shipping_address' => ['line1' => 'Synthetic rollback rehearsal only', 'country_code' => 'VN'],
    'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]]]);
$first = $create();
$reserved = $variant->inventory->fresh();
if ($reserved->quantity !== 10 || $reserved->reserved_quantity !== 2 || $first->items[0]->unit_price !== 100000) {
    throw new RuntimeException('Opening order stock/snapshot mismatch.');
}
// Same validated admin catalog service used by native Exment, not raw SQL repair.
app(CatalogEditor::class)->updateVariant($product, $variant->id, ['sku' => $variant->sku,
    'pricing_type' => 'FIXED', 'price' => 150000, 'sale_price' => null, 'status' => 'ACTIVE']);
if ($first->fresh('items')->items[0]->unit_price !== 100000) {
    throw new RuntimeException('Order snapshot changed after repricing.');
}
$first = app(ChangeOrderStatus::class)->execute($first, OrderStatus::Cancelled);
try {
    app(ChangeOrderStatus::class)->execute($first, OrderStatus::Cancelled);
    throw new RuntimeException('Duplicate cancellation unexpectedly accepted.');
} catch (ValidationException) {
    // Expected: no second stock release or history append.
}
$afterCancel = $variant->inventory->fresh();
if ($afterCancel->quantity !== 10 || $afterCancel->reserved_quantity !== 0) {
    throw new RuntimeException('Cancellation did not release exactly once.');
}
$second = $create();
foreach ([OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Shipping] as $status) {
    $second = app(ChangeOrderStatus::class)->execute($second, $status);
}
$window = $snapshot();
$save('window-'.$suffix.'.json', $window);
$delta = app(OrderDelta::class)->compare($before, $window);
if ($delta !== app(OrderDelta::class)->compare($before, $window) || count($delta['orders']) !== 2
    || $variant->inventory->fresh()->quantity !== 8 || $variant->inventory->fresh()->reserved_quantity !== 0) {
    throw new RuntimeException('Recovery manifest or final stock mismatch.');
}
$save('recovery-ledger-'.$suffix.'.json', $delta);
$summary = ['status' => 'PASS', 'scope' => 'Disposable clone only; no final migration, traffic switch or CI3 writes',
    'orders_in_window' => 2, 'initial_stock' => 10, 'order_quantity' => 2, 'after_order' => ['physical' => 10, 'reserved' => 2, 'available' => 8],
    'old_snapshot_price' => 100000, 'catalog_changed_price' => 150000, 'cancel' => ['physical' => 10, 'reserved' => 0, 'duplicate_rejected' => true],
    'shipped_order' => ['unit_price' => 150000, 'total' => 300000, 'physical' => 8, 'reserved' => 0],
    'delta_deterministic' => true, 'ledger_sha256' => hash_file('sha256', $directory.'/recovery-ledger-'.$suffix.'.json'),
    'private_artifacts' => ['baseline-'.$suffix.'.json', 'window-'.$suffix.'.json', 'recovery-ledger-'.$suffix.'.json'],
    'automatic_ci3_writes' => false, 'real_orders_in_source_modified' => false,
    'operator_requirement' => 'Match stable recovery reference before manual fulfillment; reconcile dispatched quantities before reopening CI3 writes'];
echo json_encode($summary, JSON_PRETTY_PRINT).PHP_EOL;
