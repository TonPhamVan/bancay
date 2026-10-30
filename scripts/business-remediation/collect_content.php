<?php

use App\Services\Storefront\Catalog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'bancay') {
    throw new RuntimeException('Read-only main content inventory only.');
}
$products = app(Catalog::class)->products()->get()->loadMissing('variants.image');
$queue = [];
$before = [];
foreach ($products as $product) {
    $flags = [];
    $high = false;
    if (! $product->legacy_id) {
        $flags[] = 'DEMO_RELEASE_DECISION';
        $high = true;
    }
    if ($product->images->isEmpty()) {
        $flags[] = 'NO_GALLERY';
        $high = true;
    }
    if (preg_match('/\A(?:[0-9]+|f{3,}|test)\z/i', trim($product->name))) {
        $flags[] = 'PLACEHOLDER_NAME';
        $high = true;
    }
    $text = strip_tags(($product->short_description ?? '').' '.($product->description ?? ''));
    $allContent = $text.' '.($product->meta_title ?? '').' '.($product->meta_description ?? '');
    if (preg_match('/caybonmua\.vn|cây xinh 4 mùa/iu', $allContent)) {
        $flags[] = 'LEGACY_BRAND_REVIEW';
    }
    preg_match_all('/(?<!\d)(\d[\d .]{1,20})\s*(?:₫|đ\b|vnd)/iu', $text, $prices);
    $textPrices = array_values(array_unique(array_map(fn ($value) => preg_replace('/\D/', '', $value), $prices[1])));
    if ($textPrices) {
        $flags[] = 'DESCRIPTION_PRICE_REVIEW';
    }
    if ((int) $product->legacy_id === 127) {
        $flags[] = 'KNOWN_TEST_AND_NON_PRODUCT_VARIANT_IMAGES';
        $flags[] = 'KNOWN_BODY_PRICE_MISMATCH';
        $flags[] = 'OPTION_LABEL_REVIEW';
        $high = true;
    }
    if (! $flags) {
        continue;
    }
    $snapshot = $product->toArray();
    $fingerprint = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    $before[(string) $product->id] = $snapshot;
    $queue[] = ['product_id' => $product->id, 'legacy_id' => $product->legacy_id,
        'source' => $product->legacy_id ? 'migrated' : 'demo', 'name' => $product->name,
        'url' => route('store.products.show', $product->slug, false),
        'severity' => $high ? 'HIGH' : 'MEDIUM', 'flags' => $flags,
        'current_skus' => $product->variants->map(fn ($v) => ['id' => (string) $v->id, 'sku' => $v->sku,
            'price' => $v->price === null ? null : (string) $v->price, 'sale_price' => $v->sale_price === null ? null : (string) $v->sale_price,
            'pricing_type' => $v->pricing_type->value, 'image_id' => $v->image_id])->all(),
        'image_paths' => $product->images->pluck('path')->all(), 'text_price_candidates' => $textPrices,
        'before_sha256' => $fingerprint, 'proposed_action' => $high ? 'Approve actual images/title/prices/options or explicitly approve DRAFT in release candidate; preserve local originals.' : 'Review legacy branding and contextual price text; do not auto-replace.',
        'decision' => 'PENDING', 'approved_title' => '', 'approved_image_source' => '', 'approved_price_source' => '', 'approved_by' => ''];
}
echo json_encode(['queue' => $queue, 'before' => $before], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
