<?php

use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'bancay_release_candidate_20261030_001') {
    throw new RuntimeException('Read-only verifier requires this owned remediation QA clone.');
}
$product = Product::with(['variants.inventory', 'options.values'])->findOrFail(122);
$order = Order::with(['items', 'statusHistories'])->findOrFail(260);
$migratedOrder = Order::with(['items', 'statusHistories'])->findOrFail(261);
$item = $order->items->sole();
$migratedItem = $migratedOrder->items->sole();
$repriced = $product->variants->firstWhere('id', $item->product_variant_id);
$migrated = Product::with('variants.inventory')->findOrFail($migratedItem->product_id);
$checks = [
    'two_groups_four_variants' => $product->options->count() === 2 && $product->variants->count() === 4,
    'all_four_stock_restored' => $product->variants->every(fn ($v) => $v->inventory->quantity === 10 && $v->inventory->reserved_quantity === 0),
    'repriced_catalog' => $repriced->price === 150000 && $repriced->sale_price === 110000,
    'immutable_order_snapshot' => $item->sku === 'QA-R1-SKU-4' && $item->unit_price === 90000 && $item->quantity === 2 && $item->subtotal === 180000 && $order->total === 180000,
    'matrix_order_cancelled' => $order->status->value === 'CANCELLED',
    'matrix_order_history' => $order->statusHistories->pluck('to_status')->map(fn ($s) => $s->value)->all() === ['NEW', 'CONFIRMED', 'CANCELLED'],
    'migrated_product' => (string) $migrated->legacy_id === '4' && $migratedItem->unit_price === 80000 && $migratedOrder->total === 80000,
    'migrated_stock_released' => $migrated->variants->firstWhere('id', $migratedItem->product_variant_id)->inventory->reserved_quantity === 0,
    'migrated_order_cancelled' => $migratedOrder->status->value === 'CANCELLED',
    'no_negative_or_over_reserved_inventory' => DB::table('inventories')->whereRaw('quantity < 0 OR reserved_quantity < 0 OR reserved_quantity > quantity')->count() === 0,
    'no_duplicate_fixture_orders' => Order::where('customer_name', 'like', 'QA %R1%')->count() === 2,
];
$result = ['checked_at_utc' => gmdate('c'), 'database' => DB::connection()->getDatabaseName(),
    'status' => ! in_array(false, $checks, true) ? 'PASS' : 'FAIL', 'checks' => $checks,
    'qa_product_id' => $product->id, 'qa_order_ids' => [$order->id, $migratedOrder->id],
    'legacy_product_id' => $migrated->legacy_id,
    'current_catalog' => ['price' => $repriced->price, 'sale_price' => $repriced->sale_price],
    'order_snapshot' => ['unit_price' => $item->unit_price, 'quantity' => $item->quantity, 'subtotal' => $item->subtotal],
    'fixture_note' => 'Only synthetic QA orders and cloned legacy catalog; no main/source/production writes.'];
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($result['status'] === 'PASS' ? 0 : 1);
