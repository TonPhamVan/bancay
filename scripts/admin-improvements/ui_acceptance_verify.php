<?php

use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local') || DB::connection()->getDatabaseName() !== 'bancay_release_candidate_20261031_002') {
    throw new RuntimeException('Owned UI acceptance QA clone required.');
}
$product = Product::where('slug', 'qa-ui-acceptance')->firstOrFail();
$variant = $product->variants()->where('status', 'ACTIVE')->sole();
$order = Order::where('customer_name', 'QA UI Mobile Checkout')->sole();
$facts = ['product' => $product->id, 'options' => $product->options()->count(),
    'values' => $product->options()->with('values')->get()->map(fn ($group) => $group->values->pluck('value')->all())->all(),
    'variants' => $product->variants()->count(), 'large_rows' => Product::where('slug', 'qa-ui-256')->firstOrFail()->variants()->count(),
    'images' => $product->images()->count(), 'primary_count' => $product->images()->where('is_primary', true)->count(),
    'primary_id' => $product->images()->where('is_primary', true)->sole()->id,
    'image_alt' => $product->images()->where('is_primary', true)->sole()->alt_text,
    'stock' => $variant->inventory->quantity, 'reserved' => $variant->inventory->reserved_quantity,
    'order_id' => $order->id, 'status' => $order->status->value, 'total' => $order->total,
    'item_quantity' => $order->items->sole()->quantity, 'item_price' => $order->items->sole()->unit_price,
    'history' => $order->statusHistories->pluck('to_status')->map(fn ($value) => $value instanceof BackedEnum ? $value->value : $value)->all()];
if ($facts['options'] !== 2 || $facts['variants'] !== 4 || $facts['large_rows'] !== 256 || $facts['images'] !== 2
    || $facts['primary_count'] !== 1 || $facts['image_alt'] !== 'QA accepted gallery' || $facts['stock'] !== 10 || $facts['reserved'] !== 0
    || $facts['status'] !== 'CANCELLED' || $facts['total'] !== 200000 || $facts['item_quantity'] !== 2 || $facts['item_price'] !== 100000) {
    throw new RuntimeException('QA verification mismatch: '.json_encode($facts));
}
echo json_encode(['status' => 'PASS', 'read_only' => true, 'facts' => $facts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
