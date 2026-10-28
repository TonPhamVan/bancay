<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Actions\Commerce\CreateOrder;
use App\Models\Category;
use App\Models\Setting;
use App\Services\Admin\ImageStorage;
use App\Services\Admin\ProductImages;
use App\Services\Admin\RecordEditor;
use App\Services\Admin\SettingsEditor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class RiskReviewIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function variant()
    {
        $product = app(CreateProduct::class)->execute(['name' => 'Risk review synthetic plant', 'slug' => 'review-'.fake()->unique()->numberBetween(1, 9999999),
            'category_id' => Category::factory()->create()->id, 'status' => 'PUBLISHED', 'published_at' => now()->subDay()]);

        return app(CreateVariant::class)->execute($product, ['sku' => 'REVIEW-'.$product->id, 'pricing_type' => 'FIXED', 'price' => 100000, 'quantity' => 10]);
    }

    private function order()
    {
        $variant = $this->variant();

        return app(CreateOrder::class)->execute(['customer_name' => 'Synthetic review', 'customer_phone' => '0900000000',
            'shipping_address' => ['line1' => 'Synthetic review address', 'country_code' => 'VN'],
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]]]);
    }

    public function test_database_rejects_null_fixed_price_even_when_validation_is_bypassed(): void
    {
        $variant = $this->variant();
        $this->expectException(QueryException::class);
        DB::table('product_variants')->where('id', $variant->id)->update(['price' => null]);
    }

    public function test_database_rejects_variant_money_outside_php_integer_domain(): void
    {
        $variant = $this->variant();
        $this->expectException(QueryException::class);
        DB::table('product_variants')->where('id', $variant->id)->update(['price' => '9223372036854775808']);
    }

    public function test_database_rejects_order_money_outside_php_integer_domain(): void
    {
        $order = $this->order();
        $this->expectException(QueryException::class);
        DB::table('orders')->where('id', $order->id)->update(['subtotal' => '9223372036854775808', 'total' => '9223372036854775808']);
    }

    public function test_database_rejects_item_money_outside_php_integer_domain(): void
    {
        $order = $this->order();
        $this->expectException(QueryException::class);
        DB::table('order_items')->where('order_id', $order->id)->update(['unit_price' => '9223372036854775808', 'subtotal' => '9223372036854775808']);
    }

    public function test_replacing_category_image_preserves_file_used_by_product(): void
    {
        Storage::fake('public');
        $variant = $this->variant();
        $path = 'products/shared.webp';
        Storage::disk('public')->put($path, 'synthetic existing image');
        $variant->product->images()->create(['path' => $path]);
        $category = $variant->product->category;
        $category->update(['image' => $path]);
        app(RecordEditor::class)->save('catalog', 'categories', ['name' => $category->name, 'slug' => $category->slug,
            'sort_order' => 0, 'featured' => 0, 'status' => 'DRAFT', 'image_upload' => UploadedFile::fake()->image('replacement.jpg')], $category->id);
        // RefreshDatabase's outer transaction defers callbacks; invoke registered cleanup callbacks explicitly.
        foreach (app('db.transactions')->getPendingTransactions()->flatMap(fn ($tx) => $tx->getCallbacks()) as $callback) {
            $callback();
        }
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertExists($category->fresh()->image);
    }

    public function test_deleting_one_image_reference_preserves_another_products_image(): void
    {
        Storage::fake('public');
        $first = $this->variant()->product;
        $second = $this->variant()->product;
        $path = 'products/shared.webp';
        Storage::disk('public')->put($path, 'synthetic image');
        $image = $first->images()->create(['path' => $path]);
        $second->images()->create(['path' => $path]);
        app(ProductImages::class)->delete($first, $image->id);
        foreach (app('db.transactions')->getPendingTransactions()->flatMap(fn ($tx) => $tx->getCallbacks()) as $callback) {
            $callback();
        }
        Storage::disk('public')->assertExists($path);
    }

    public function test_shared_logo_reference_is_preserved_until_last_reference_is_removed(): void
    {
        Storage::fake('public');
        $path = 'settings/shared.webp';
        Storage::disk('public')->put($path, 'synthetic image');
        Setting::create(['key' => 'logo', 'value' => $path]);
        app(ImageStorage::class)->deleteIfUnused($path);
        Storage::disk('public')->assertExists($path);
        Setting::where('key', 'logo')->delete();
        app(ImageStorage::class)->deleteIfUnused($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_setting_logo_replacement_keeps_shared_product_image(): void
    {
        Storage::fake('public');
        $path = 'products/logo-shared.webp';
        Storage::disk('public')->put($path, 'synthetic image');
        $this->variant()->product->images()->create(['path' => $path]);
        Setting::create(['key' => 'logo', 'value' => $path]);
        app(SettingsEditor::class)->save(['settings' => ['site_name' => 'Synthetic review'], 'logo_upload' => UploadedFile::fake()->image('logo.jpg')]);
        foreach (app('db.transactions')->getPendingTransactions()->flatMap(fn ($tx) => $tx->getCallbacks()) as $callback) {
            $callback();
        }
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertExists(Setting::where('key', 'logo')->value('value'));
    }

    public function test_maximum_supported_integer_remains_exact_in_order_snapshots(): void
    {
        $variant = $this->variant();
        $variant->update(['price' => PHP_INT_MAX]);
        $order = app(CreateOrder::class)->execute(['customer_name' => 'Synthetic boundary', 'customer_phone' => '0900000000',
            'shipping_address' => ['line1' => 'Synthetic', 'country_code' => 'VN'], 'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]]]);
        $this->assertSame(PHP_INT_MAX, $order->fresh()->total);
        $this->assertSame(PHP_INT_MAX, $order->fresh('items')->items[0]->unit_price);
    }
}
