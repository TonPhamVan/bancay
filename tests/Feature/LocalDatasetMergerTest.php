<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Legacy\LocalDatasetMerger;
use App\Legacy\MigrationFailure;
use App\Legacy\TargetGuard;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LocalDatasetMergerTest extends TestCase
{
    use RefreshDatabase;

    private function datasets(): array
    {
        $category = Category::factory()->create(['legacy_id' => 33, 'slug' => 'source-category', 'status' => 'DRAFT', 'published_at' => null]);
        $articleCategory = ArticleCategory::factory()->create(['legacy_id' => 9, 'slug' => 'source-articles', 'status' => 'DRAFT', 'published_at' => null]);
        $product = app(CreateProduct::class)->execute(['name' => 'Source product', 'slug' => 'source-product', 'category_id' => $category->id,
            'status' => 'DRAFT', 'options' => [['name' => 'Size', 'values' => ['S', 'M']]]]);
        $product->update(['legacy_id' => 4]);
        foreach ($product->options->first()->values as $i => $value) {
            app(CreateVariant::class)->execute($product, ['sku' => 'SOURCE-'.$i, 'pricing_type' => 'FIXED', 'price' => 100000 + $i, 'quantity' => 8,
                'option_value_ids' => [$value->id], 'status' => 'ACTIVE']);
        }
        Article::factory()->create(['legacy_id' => 29, 'slug' => 'source-article', 'article_category_id' => $articleCategory->id, 'status' => 'DRAFT', 'published_at' => null]);
        $order = Order::create(['legacy_id' => 80, 'legacy_status' => 1, 'is_legacy' => true, 'order_number' => 'LEG-80',
            'customer_name' => 'Synthetic history', 'customer_phone' => '0900000000', 'shipping_address' => ['line1' => 'Synthetic address'],
            'currency' => 'VND', 'subtotal' => 200000, 'discount_amount' => 0, 'shipping_fee' => 0, 'total' => 200000, 'status' => 'CONFIRMED']);
        $order->items()->create(['product_id' => $product->id, 'product_variant_id' => null, 'product_name' => 'Recorded source name',
            'sku' => null, 'unit_price' => 100000, 'quantity' => 2, 'subtotal' => 200000]);
        $order->statusHistories()->create(['from_status' => null, 'to_status' => 'CONFIRMED', 'changed_by' => null, 'note' => 'Historical record']);
        Setting::create(['key' => 'site_name', 'value' => 'Original source brand']);
        $source = [];
        foreach (LocalDatasetMerger::TABLES as $table) {
            $source[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        foreach (array_reverse(LocalDatasetMerger::TABLES) as $table) {
            DB::table($table)->delete();
        }
        $demo = Product::factory()->create(['name' => 'Keep demo', 'slug' => 'demo-product']);
        $variant = app(CreateVariant::class)->execute($demo, ['sku' => 'DEMO-1', 'pricing_type' => 'FIXED', 'price' => 99000, 'quantity' => 9, 'option_value_ids' => []]);
        $variant->inventory->update(['reserved_quantity' => 2]);
        Setting::create(['key' => 'site_name', 'value' => 'Keep demo brand']);

        return [$source, $demo, $variant];
    }

    public function test_copy_remaps_relations_combinations_and_history_without_changing_demo(): void
    {
        [$source, $demo, $demoVariant] = $this->datasets();
        $hash = hash('sha256', json_encode($source));
        $result = (new LocalDatasetMerger)->apply($source, true);
        $copy = Product::where('legacy_id', 4)->firstOrFail();
        $this->assertNotSame((int) $source['products'][0]['id'], $copy->id);
        $this->assertSame('PUBLISHED', $copy->status->value);
        $this->assertSame(33, (int) $copy->category->legacy_id);
        $this->assertSame('Keep demo', $demo->fresh()->name);
        $this->assertSame(99000, $demoVariant->fresh()->price);
        $this->assertSame(9, $demoVariant->inventory->fresh()->quantity);
        $this->assertSame(2, $demoVariant->inventory->fresh()->reserved_quantity);
        $this->assertSame('Keep demo brand', Setting::where('key', 'site_name')->value('value'));
        $this->assertContains('site_name', $result['retained_existing_setting_keys']);
        foreach ($copy->variants()->with('optionValues', 'inventory')->get() as $variant) {
            $ids = $variant->optionValues->pluck('id')->all();
            sort($ids, SORT_NUMERIC);
            $this->assertSame(hash('sha256', implode(':', $ids)), $variant->combination_key);
            $this->assertSame(0, $variant->inventory->reserved_quantity);
        }
        $order = Order::where('legacy_id', 80)->firstOrFail();
        $this->assertTrue($order->is_legacy);
        $this->assertSame((int) $copy->id, (int) $order->items->first()->product_id);
        $this->assertNull($order->items->first()->product_variant_id);
        $this->assertSame('Recorded source name', $order->items->first()->product_name);
        $this->assertSame(100000, $order->items->first()->unit_price);
        $this->assertCount(1, $order->statusHistories);
        $this->assertSame($hash, hash('sha256', json_encode($source)));
        $this->expectException(MigrationFailure::class);
        (new LocalDatasetMerger)->apply($source, true); // Cannot duplicate an already copied root/SKU.
    }

    public function test_copy_failure_rolls_back_all_new_roots_and_children(): void
    {
        [$source] = $this->datasets();
        $before = app(TargetGuard::class)->fingerprint();
        $source['inventories'][0]['quantity'] = -1;
        try {
            (new LocalDatasetMerger)->apply($source, true);
            $this->fail('Invalid stock copied');
        } catch (QueryException|MigrationFailure) {
            $this->assertSame($before, app(TargetGuard::class)->fingerprint());
            $this->assertDatabaseCount('products', 1);
            $this->assertDatabaseCount('orders', 0);
        }
    }
}
