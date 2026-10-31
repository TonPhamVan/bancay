<?php

/** Read-only checks of the explicitly owned B1 QA clone; never run against main/source. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\Product;
use App\Services\Storefront\Catalog;
use Exceedone\Exment\Model\Plugin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (! $app->environment('local') || DB::connection()->getDatabaseName() !== 'bancay_release_candidate_20261030_003') {
    throw new RuntimeException('Owned B1 QA clone required.');
}
$product = Product::where('slug', 'qa-b1-cay-thu-nghiem')->firstOrFail();
$order = Order::where('customer_name', 'QA B1 Synthetic Buyer')->sole();
$variant = $product->variants()->where('status', 'ACTIVE')->sole();
$stock = $variant->inventory;
$item = $order->items()->sole();
$facts = [
    'fixture_product_id' => $product->id,
    'options' => $product->options()->count(), 'variants' => $product->variants()->count(),
    'active_variants' => $product->variants()->where('status', 'ACTIVE')->count(),
    'order_count_for_fixture' => Order::where('customer_name', 'QA B1 Synthetic Buyer')->count(),
    'order_status' => $order->status->value, 'order_total' => $order->total,
    'snapshot_price' => $item->unit_price, 'snapshot_quantity' => $item->quantity,
    'history' => $order->statusHistories()->orderBy('id')->pluck('to_status')->map(fn ($status) => $status->value)->all(),
    'physical_stock' => $stock->quantity, 'reserved_stock' => $stock->reserved_quantity, 'available_stock' => $stock->available(),
    'active_plugins' => Plugin::where('plugin_name', 'like', 'Bancay%')->where('active_flg', 1)->count(),
    'public_count' => app(Catalog::class)->products()->count(),
];
if ($facts['options'] !== 2 || $facts['variants'] !== 4 || $facts['active_variants'] !== 1
    || $facts['order_count_for_fixture'] !== 1 || $facts['order_status'] !== 'CANCELLED' || $facts['order_total'] !== 200000
    || $facts['snapshot_price'] !== 100000 || $facts['snapshot_quantity'] !== 2
    || $facts['history'] !== ['NEW', 'CONFIRMED', 'CANCELLED']
    || $facts['physical_stock'] !== 10 || $facts['reserved_stock'] !== 0 || $facts['available_stock'] !== 10
    || $facts['active_plugins'] !== 6 || $facts['public_count'] !== 120) {
    throw new RuntimeException('B1 QA facts mismatch: '.json_encode($facts));
}
echo json_encode(['status' => 'PASS', 'read_only' => true, 'facts' => $facts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
