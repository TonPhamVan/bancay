<?php

use App\Models\Banner;
use App\Models\Product;
use App\Services\Storefront\Catalog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local') || DB::connection()->getDatabaseName() !== 'bancay_release_candidate_20261031_001') {
    throw new RuntimeException('Owned UX2 QA clone required.');
}
$product = Product::where('slug', 'qa-ux2-cay-thu-nghiem')->firstOrFail();
$images = $product->images()->get();
$variant = $product->variants()->where('status', 'ACTIVE')->sole();
$facts = [
    'fixture_product_id' => $product->id,
    'publication' => $product->status->value,
    'options' => $product->options()->count(), 'variants' => $product->variants()->count(),
    'images' => $images->count(), 'primary_count' => $images->where('is_primary', true)->count(),
    'ordered_image_ids' => $images->pluck('id')->all(), 'primary_image_id' => $images->firstWhere('is_primary', true)?->id,
    'primary_alt_matches_attempt' => $images->firstWhere('is_primary', true)?->alt_text === 'QA UX2 ảnh chính ý tưởng',
    'persisted_price' => $variant->price, 'physical_stock' => $variant->inventory->quantity,
    'reserved_stock' => $variant->inventory->reserved_quantity,
    'variant_image_owned' => $images->contains('id', $variant->image_id),
    'public_count' => app(Catalog::class)->products()->count(),
    'active_banner_positions' => Banner::where('status', 'ACTIVE')->selectRaw('position, count(*) AS count')->groupBy('position')->pluck('count', 'position')->all(),
];
if ($facts['publication'] !== 'DRAFT' || $facts['options'] !== 2 || $facts['variants'] !== 4 || $facts['images'] !== 2
    || $facts['primary_count'] !== 1 || ! $facts['primary_alt_matches_attempt'] || $facts['persisted_price'] !== 100000
    || $facts['physical_stock'] !== 10 || $facts['reserved_stock'] !== 0 || ! $facts['variant_image_owned']) {
    throw new RuntimeException('UX2 QA facts mismatch: '.json_encode($facts));
}
echo json_encode(['status' => 'PASS', 'read_only' => true, 'facts' => $facts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
