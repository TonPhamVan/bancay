<?php

use App\Models\Article;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\InventoryService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'bancay_phase4_ui') {
    throw new RuntimeException('Disposable QA clone only');
}
$product = Product::where('legacy_id', 4)->firstOrFail();
$product->update(['status' => 'PUBLISHED', 'published_at' => now()->subMinute(), 'featured' => true]);
$category = $product->category;
for ($node = $category; $node; $node = $node->parent) {
    $node->update(['status' => 'PUBLISHED', 'published_at' => now()->subMinute(), 'featured' => true]);
}
$article = Article::whereNotNull('legacy_id')->where('content', 'like', '%/storage/articles/legacy/%')->firstOrFail();
$article->update(['status' => 'PUBLISHED', 'published_at' => now()->subMinute()]);
for ($node = $article->category; $node; $node = $node->parent) {
    $node->update(['status' => 'PUBLISHED', 'published_at' => now()->subMinute()]);
}
Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'Phase4 acceptance clone']);
$oos = ProductVariant::where('pricing_type', 'FIXED')->whereHas('inventory', fn ($q) => $q->where('quantity', 0))->with('product.category')->first();
if (! $oos) {
    $oos = ProductVariant::where('pricing_type', 'FIXED')->whereHas('product', fn ($q) => $q->where('legacy_id', '!=', 4))->with(['inventory', 'product.category'])->first();
    if ($oos) {
        app(InventoryService::class)->adjust($oos->id, -$oos->inventory->quantity);
    }
}
if ($oos) {
    $oos->product->update(['status' => 'PUBLISHED', 'published_at' => now()->subMinute()]);
    for ($node = $oos->product->category; $node; $node = $node->parent) {
        $node->update(['status' => 'PUBLISHED', 'published_at' => now()->subMinute()]);
    }
}
$order = Order::where('is_legacy', true)->with('items')->firstOrFail();
$reviewedProducts = Product::whereIn('legacy_id', [7, 10])->get(['id', 'legacy_id', 'category_id'])->toArray();
$reviewedArticles = Article::whereIn('legacy_id', [29, 31, 48, 63, 67, 69, 70])->get(['id', 'legacy_id', 'article_category_id'])->toArray();
$recovered = Product::where('legacy_id', 8)->firstOrFail();
$recovered->update(['status' => 'PUBLISHED', 'published_at' => now()->subMinute()]);
for ($node = $recovered->category; $node; $node = $node->parent) {
    $node->update(['status' => 'PUBLISHED', 'published_at' => now()->subMinute()]);
}
$fixture = ['product' => ['id' => $product->id, 'legacy_id' => $product->legacy_id, 'slug' => $product->slug], 'category' => ['id' => $category->id, 'slug' => $category->slug, 'description_characters' => mb_strlen($category->description)],
    'article' => ['id' => $article->id, 'slug' => $article->slug], 'historical_order' => ['id' => $order->id, 'number' => $order->order_number, 'unit_price' => $order->items[0]->unit_price, 'quantity' => $order->items[0]->quantity],
    'oos_product_slug' => $oos?->product?->slug, 'baseline_reserved' => Inventory::sum('reserved_quantity'),
    'reviewed_products' => $reviewedProducts, 'reviewed_articles' => $reviewedArticles, 'recovered_product_slug' => $recovered->slug];
$fixture['reviewed_publication'] = env('BANCAY_QA_DATASET') === 'combined' ? 'PUBLISHED' : 'DRAFT';
file_put_contents(base_path('legacy-private/phase4/ui/fixture.json'), json_encode($fixture, JSON_PRETTY_PRINT));
chmod(base_path('legacy-private/phase4/ui/fixture.json'), 0600);
echo "QA fixtures prepared; only the disposable clone is published.\n";
