<?php

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Actions\Commerce\CreateOrder;
use App\Services\Admin\CatalogEditor;
use App\Services\Admin\ProductImages;
use App\Services\Admin\RecordEditor;
use App\Services\Admin\StockAdjustment;
use Exceedone\Exment\Model\LoginUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local') || DB::connection()->getDatabaseName() !== 'bancay_release_candidate_20261031_002'
    || config('app.url') !== 'http://localhost:8004' || config('mail.default') !== 'log') {
    throw new RuntimeException('Owned UI acceptance QA clone required.');
}
if (DB::table('products')->where('slug', 'qa-ui-acceptance')->exists()) {
    throw new RuntimeException('Fixture already exists; no replay.');
}
$editor = app(RecordEditor::class);
$category = $editor->save('catalog', 'categories', ['name' => 'QA UI acceptance', 'slug' => 'qa-ui-acceptance-category',
    'status' => 'PUBLISHED', 'published_at' => now()->subDay(), 'sort_order' => 0, 'featured' => 0]);
$product = app(CreateProduct::class)->execute(['name' => 'QA UI Acceptance Plant', 'slug' => 'qa-ui-acceptance',
    'category_id' => $category->id, 'status' => 'DRAFT', 'description' => '<p>QA synthetic content.</p>',
    'options' => [['name' => 'Kích thước', 'values' => ['Nhỏ', 'Lớn']], ['name' => 'Màu chậu', 'values' => ['Trắng', 'Nâu']]]]);
$files = [];
foreach (['balcony-d47c632faafd.webp', 'water-feature-f778c80b9852.webp'] as $name) {
    $files[] = new UploadedFile(public_path('storefront/landscape-ideas/'.$name), $name, 'image/webp', null, true);
}
app(ProductImages::class)->upload($product, $files);
app(CatalogEditor::class)->generate($product, ['sku_prefix' => 'QA-UI', 'confirm' => 1]);
$variant = $product->variants()->firstOrFail();
app(CatalogEditor::class)->updateVariant($product, $variant->id, ['sku' => $variant->sku, 'pricing_type' => 'FIXED', 'price' => 100000,
    'status' => 'ACTIVE', 'image_id' => $product->images()->first()->id]);
app(StockAdjustment::class)->execute($variant->inventory, ['quantity' => 10, 'expected_quantity' => 0, 'reason' => 'QA UI synthetic stock'], LoginUser::query()->firstOrFail()->id);

$large = app(CreateProduct::class)->execute(['name' => 'QA UI256 matrix', 'slug' => 'qa-ui-256', 'category_id' => $category->id,
    'status' => 'DRAFT', 'options' => [['name' => 'A', 'values' => array_map('strval', range(1, 16))], ['name' => 'B', 'values' => array_map('strval', range(1, 16))]]]);
app(CatalogEditor::class)->generate($large, ['sku_prefix' => 'QA-256', 'confirm' => 1]);

$orderProduct = app(CreateProduct::class)->execute(['name' => 'QA UI order fixture', 'slug' => 'qa-ui-order-fixture', 'category_id' => $category->id,
    'status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
$orderVariant = app(CreateVariant::class)->execute($orderProduct, ['sku' => 'QA-UI-ORDER', 'pricing_type' => 'FIXED', 'price' => 100000, 'quantity' => 50, 'status' => 'ACTIVE']);
$orders = [];
foreach ([1, 2] as $number) {
    $orders[] = app(CreateOrder::class)->execute(['customer_name' => 'QA UI Pending '.$number, 'customer_phone' => '0900000000',
        'shipping_address' => ['line1' => 'QA Synthetic Address', 'country_code' => 'VN'],
        'items' => [['product_variant_id' => $orderVariant->id, 'quantity' => 1]]])->id;
}
$articleCategory = $editor->save('content', 'article-categories', ['name' => 'QA UI Articles', 'slug' => 'qa-ui-articles',
    'status' => 'PUBLISHED', 'published_at' => now()->subDay(), 'sort_order' => 0, 'show_in_menu' => 0]);
$article = $editor->save('content', 'articles', ['title' => 'QA UI Draft Article', 'slug' => 'qa-ui-draft-article',
    'article_category_id' => $articleCategory->id, 'status' => 'DRAFT', 'featured' => 0, 'show_in_menu' => 0,
    'content' => '<p>QA article preview body.</p>']);
echo json_encode(['product_id' => $product->id, 'large_product_id' => $large->id, 'order_ids' => $orders, 'article_id' => $article->id,
    'category_id' => $category->id, 'large_rows' => $large->variants()->count()], JSON_PRETTY_PRINT).PHP_EOL;
