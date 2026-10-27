<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Actions\Commerce\ChangeOrderStatus;
use App\Actions\Commerce\CreateOrder;
use App\Actions\System\SetPublicSetting;
use App\Domain\Commerce\OrderStatus;
use App\Http\Requests\StoreProductRequest;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantValue;
use App\Rules\PublicImagePath;
use App\Services\CategoryTreeService;
use App\Services\InventoryService;
use Database\Seeders\DemoDataSeeder;
use Exceedone\Exment\Model\LoginUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $options = []): Product
    {
        return app(CreateProduct::class)->execute([
            'category_id' => Category::factory()->create()->id, 'name' => 'Synthetic Plant',
            'slug' => 'synthetic-'.fake()->unique()->slug(), 'status' => 'PUBLISHED',
            'published_at' => now()->subMinute()->toDateTimeString(), 'options' => $options,
        ]);
    }

    private function variant(?Product $product = null, array $overrides = []): ProductVariant
    {
        return app(CreateVariant::class)->execute($product ?? $this->product(), array_merge([
            'sku' => 'TEST-'.fake()->unique()->numberBetween(1000000, 9999999),
            'pricing_type' => 'FIXED', 'price' => 120000, 'sale_price' => 99000, 'quantity' => 10,
        ], $overrides));
    }

    private function orderInput(int $variantId, int $quantity = 2): array
    {
        return [
            'customer_name' => 'Synthetic Guest', 'customer_phone' => '+84999999001',
            'shipping_address' => ['line1' => 'Synthetic address', 'country_code' => 'VN'],
            'items' => [['product_variant_id' => $variantId, 'quantity' => $quantity]],
        ];
    }

    public function test_health_and_exment_routes_are_registered_without_storefront(): void
    {
        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
        $this->assertTrue(app('router')->getRoutes()->hasNamedRoute('health'));
        $this->assertTrue(collect(app('router')->getRoutes())->contains(fn ($r) => str_starts_with($r->uri(), 'admin/')));
        $this->get('/')->assertNotFound();
    }

    public function test_synthetic_seed_builds_relationships_and_is_idempotent(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_options', 2);
        $this->assertDatabaseCount('product_option_values', 4);
        $this->assertDatabaseCount('product_variants', 4);
        $this->assertDatabaseCount('product_variant_values', 8);
        $this->assertDatabaseCount('inventories', 4);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('customer_addresses', 1);
        $this->assertCount(2, Order::firstOrFail()->statusHistories);
        $this->assertCount(2, Product::firstOrFail()->images);
        $this->assertCount(2, Product::firstOrFail()->variants()->firstOrFail()->optionValues);
    }

    public function test_simple_variant_has_one_inventory_and_no_json_options(): void
    {
        $variant = $this->variant();
        $this->assertSame(10, $variant->inventory->available());
        $this->assertCount(0, $variant->optionValues);
        $this->assertSame(99000, $variant->effectivePrice());
        $this->assertSame($variant->product_id, $variant->product->id);
    }

    public function test_vietnamese_options_slug_and_full_utf8_round_trip(): void
    {
        $name = 'Phân bón GreenHome 🌱';
        $product = app(CreateProduct::class)->execute([
            'category_id' => Category::factory()->create()->id, 'name' => $name,
            'status' => 'PUBLISHED', 'published_at' => now()->subMinute()->toDateTimeString(),
            'options' => [
                ['name' => 'Trọng lượng', 'values' => ['500g', '1kg']],
                ['name' => 'Loại cây', 'values' => ['Hoa hồng', 'Lan']],
            ],
        ]);
        $this->assertSame($name, $product->fresh()->name);
        $this->assertSame('phan-bon-greenhome', $product->slug);
        $variant = $this->variant($product, ['option_value_ids' => [$product->options[0]->values[0]->id, $product->options[1]->values[0]->id]]);
        $order = app(CreateOrder::class)->execute($this->orderInput($variant->id));
        $this->assertStringContainsString('Trọng lượng: 500g', $order->items[0]->variant_description);
        $this->assertStringContainsString('Loại cây: Hoa hồng', $order->items[0]->variant_description);
    }

    public function test_customer_has_only_one_default_address(): void
    {
        $customer = Customer::factory()->create();
        $address = ['recipient_name' => 'Synthetic name', 'phone' => '+84999999001', 'line1' => 'Synthetic address', 'is_default' => true];
        $customer->addresses()->create($address);
        $this->expectException(QueryException::class);
        $customer->addresses()->create($address);
    }

    public function test_banner_scope_is_not_promoted_when_category_is_deleted(): void
    {
        $category = Category::factory()->create();
        Banner::factory()->create(['category_id' => $category->id]);
        $this->expectException(QueryException::class);
        $category->delete();
    }

    public function test_sku_is_globally_unique_at_validation_and_database(): void
    {
        $variant = $this->variant();
        $this->expectException(ValidationException::class);
        $this->variant(overrides: ['sku' => $variant->sku]);
    }

    public function test_duplicate_combination_is_rejected(): void
    {
        $variant = $this->variant();
        $this->expectException(ValidationException::class);
        $this->variant($variant->product);
    }

    public function test_sku_unique_constraint_rejects_bypassed_validation(): void
    {
        $first = $this->variant();
        $copy = $first->replicate();
        $copy->product_id = $this->product()->id;
        $this->expectException(QueryException::class);
        $copy->save();
    }

    public function test_product_requires_existing_category_foreign_key(): void
    {
        $this->expectException(QueryException::class);
        Product::factory()->create(['category_id' => 999999999]);
    }

    public function test_guest_cannot_attach_order_to_arbitrary_customer_id(): void
    {
        $data = $this->orderInput($this->variant()->id);
        $data['customer_id'] = 1;
        $this->expectException(ValidationException::class);
        app(CreateOrder::class)->execute($data);
    }

    public function test_variant_image_must_belong_to_same_product_in_database(): void
    {
        $variant = $this->variant();
        $otherImage = $this->product()->images()->create(['path' => 'products/other.jpg']);
        $this->expectException(QueryException::class);
        $variant->update(['image_id' => $otherImage->id]);
    }

    public function test_header_injection_email_and_newline_image_paths_are_rejected(): void
    {
        $data = $this->orderInput($this->variant()->id);
        $data['customer_email'] = "buyer@example.test\r\nInjected: value";
        $this->assertTrue(Validator::make($data, CreateOrder::rules())->fails());
        $this->assertTrue(Validator::make(['image' => "products/a.jpg\n"], ['image' => [new PublicImagePath]])->fails());
    }

    public function test_invalid_history_actor_rolls_back_order_and_stock(): void
    {
        $variant = $this->variant();
        try {
            app(CreateOrder::class)->execute($this->orderInput($variant->id), actorId: 999999999);
            $this->fail('Invalid actor FK was allowed.');
        } catch (QueryException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame(0, $variant->inventory->fresh()->reserved_quantity);
        }
    }

    public function test_hard_deleted_catalog_links_become_null_without_losing_snapshot(): void
    {
        $variant = $this->variant();
        $order = app(CreateOrder::class)->execute($this->orderInput($variant->id));
        app(ChangeOrderStatus::class)->execute($order, OrderStatus::Cancelled);
        // No hard-delete UI exists; this exercises FK semantics after safe stock cleanup.
        $variant->inventory()->delete();
        $variant->delete();
        $variant->product->forceDelete();
        $item = $order->fresh('items')->items[0];
        $this->assertNull($item->product_id);
        $this->assertNull($item->product_variant_id);
        $this->assertSame(99000, $item->unit_price);
        $this->assertSame('Synthetic Plant', $item->product_name);
    }

    public function test_variant_requires_one_value_per_own_option(): void
    {
        $product = $this->product([['name' => 'Weight', 'values' => ['500g', '1kg']], ['name' => 'Plant', 'values' => ['Rose']]]);
        $ids = $product->options[0]->values->pluck('id')->all();
        $this->expectException(ValidationException::class);
        $this->variant($product, ['option_value_ids' => $ids]);
    }

    public function test_composite_fk_blocks_cross_product_option_values(): void
    {
        $variant = $this->variant();
        $other = $this->product([['name' => 'Size', 'values' => ['Small']]]);
        $value = $other->options[0]->values[0];
        $this->expectException(QueryException::class);
        ProductVariantValue::create([
            'product_variant_id' => $variant->id, 'product_id' => $variant->product_id,
            'product_option_id' => $value->product_option_id, 'product_option_value_id' => $value->id,
        ]);
    }

    public function test_database_rejects_invalid_sale_price(): void
    {
        $variant = $this->variant();
        $this->expectException(QueryException::class);
        $variant->update(['sale_price' => 150000]);
    }

    public function test_contact_price_cannot_be_ordered_as_free(): void
    {
        $variant = $this->variant(overrides: ['pricing_type' => 'CONTACT', 'price' => null, 'sale_price' => null]);
        $this->assertNull($variant->effectivePrice());
        $this->expectException(ValidationException::class);
        app(CreateOrder::class)->execute($this->orderInput($variant->id));
    }

    public function test_client_price_and_invalid_quantity_are_rejected(): void
    {
        $variant = $this->variant();
        $data = $this->orderInput($variant->id, 0);
        $data['items'][0]['unit_price'] = 1;
        $validator = Validator::make($data, CreateOrder::rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('items.0', $validator->errors()->toArray());
        $this->assertArrayHasKey('items.0.quantity', $validator->errors()->toArray());
    }

    public function test_guest_order_snapshots_price_name_sku_and_reserves_stock(): void
    {
        $variant = $this->variant();
        $order = app(CreateOrder::class)->execute($this->orderInput($variant->id));
        $this->assertNull($order->customer_id);
        $this->assertSame(198000, $order->total);
        $this->assertSame(99000, $order->items[0]->unit_price);
        $this->assertSame($variant->sku, $order->items[0]->sku);
        $originalSku = $variant->sku;
        $variant->update(['price' => 150000, 'sale_price' => 120000, 'sku' => 'RENAMED-'.$variant->id]);
        $variant->product->update(['name' => 'Renamed product']);
        $this->assertSame(99000, $order->fresh('items')->items[0]->unit_price);
        $this->assertSame('Synthetic Plant', $order->fresh('items')->items[0]->product_name);
        $this->assertSame($originalSku, $order->fresh('items')->items[0]->sku);
        $this->assertSame(2, $variant->inventory->fresh()->reserved_quantity);
        $this->assertSame(OrderStatus::New, $order->status);
        $this->assertNull($order->statusHistories[0]->from_status);
    }

    public function test_second_order_cannot_oversell_reserved_stock(): void
    {
        $variant = $this->variant(overrides: ['quantity' => 2]);
        app(CreateOrder::class)->execute($this->orderInput($variant->id));
        try {
            app(CreateOrder::class)->execute($this->orderInput($variant->id));
            $this->fail('Overselling was allowed.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 1);
            $this->assertSame(2, $variant->inventory->fresh()->reserved_quantity);
        }
    }

    public function test_failed_multiline_order_rolls_back_all_reservations(): void
    {
        $first = $this->variant();
        $second = $this->variant(overrides: ['quantity' => 0]);
        $data = $this->orderInput($first->id);
        $data['items'][] = ['product_variant_id' => $second->id, 'quantity' => 1];
        try {
            app(CreateOrder::class)->execute($data);
            $this->fail('Insufficient stock was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame(0, $first->inventory->fresh()->reserved_quantity);
        }
    }

    public function test_status_history_and_basic_inventory_lifecycle(): void
    {
        $variant = $this->variant();
        $order = app(CreateOrder::class)->execute($this->orderInput($variant->id));
        foreach ([OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Shipping, OrderStatus::Completed, OrderStatus::Returned] as $status) {
            $order = app(ChangeOrderStatus::class)->execute($order, $status);
        }
        $this->assertCount(6, $order->statusHistories);
        $this->assertSame(OrderStatus::Completed, $order->statusHistories->last()->from_status);
        $this->assertSame(8, $variant->inventory->fresh()->quantity);
        $this->assertSame(0, $variant->inventory->fresh()->reserved_quantity);
    }

    public function test_cancel_releases_reservation_once(): void
    {
        $variant = $this->variant();
        $order = app(CreateOrder::class)->execute($this->orderInput($variant->id));
        $order = app(ChangeOrderStatus::class)->execute($order, OrderStatus::Cancelled);
        $this->assertSame(10, $variant->inventory->fresh()->quantity);
        $this->assertSame(0, $variant->inventory->fresh()->reserved_quantity);
        $this->expectException(ValidationException::class);
        app(ChangeOrderStatus::class)->execute($order, OrderStatus::Cancelled);
    }

    public function test_invalid_transition_does_not_write_history_or_touch_stock(): void
    {
        $variant = $this->variant();
        $order = app(CreateOrder::class)->execute($this->orderInput($variant->id));
        try {
            app(ChangeOrderStatus::class)->execute($order, OrderStatus::Completed);
            $this->fail('Order skipped all workflow steps.');
        } catch (ValidationException) {
            $this->assertCount(1, $order->fresh('statusHistories')->statusHistories);
            $this->assertSame(OrderStatus::New, $order->fresh()->status);
            $this->assertSame(2, $variant->inventory->fresh()->reserved_quantity);
        }
    }

    public function test_stock_adjustment_cannot_invalidate_reservation(): void
    {
        $variant = $this->variant();
        app(CreateOrder::class)->execute($this->orderInput($variant->id));
        $this->expectException(ValidationException::class);
        app(InventoryService::class)->adjust($variant->id, -9);
    }

    public function test_database_rejects_reserved_quantity_above_stock(): void
    {
        $variant = $this->variant();
        $this->expectException(QueryException::class);
        $variant->inventory->update(['reserved_quantity' => 11]);
    }

    public function test_order_item_snapshot_is_immutable(): void
    {
        $order = app(CreateOrder::class)->execute($this->orderInput($this->variant()->id));
        $this->expectException(\LogicException::class);
        $order->items[0]->update(['unit_price' => 1]);
    }

    public function test_status_history_is_append_only(): void
    {
        $order = app(CreateOrder::class)->execute($this->orderInput($this->variant()->id));
        $this->expectException(\LogicException::class);
        $order->statusHistories[0]->delete();
    }

    public function test_soft_archived_catalog_does_not_remove_order_history(): void
    {
        $variant = $this->variant();
        $order = app(CreateOrder::class)->execute($this->orderInput($variant->id));
        $variant->product->delete();
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame('Synthetic Plant', $order->fresh('items')->items[0]->product_name);
        $this->assertNotNull($order->fresh('items')->items[0]->product);
        $this->expectException(ValidationException::class);
        app(CreateOrder::class)->execute($this->orderInput($variant->id));
    }

    public function test_missing_fk_and_duplicate_primary_image_are_blocked(): void
    {
        $product = $this->product();
        $product->images()->create(['path' => 'products/a.jpg', 'is_primary' => true]);
        $this->expectException(QueryException::class);
        $product->images()->create(['path' => 'products/b.jpg', 'is_primary' => true]);
    }

    public function test_category_parent_cannot_become_its_descendant(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $this->expectException(ValidationException::class);
        app(CategoryTreeService::class)->move($parent, $child->id);
    }

    public function test_article_category_tree_has_the_same_cycle_guard(): void
    {
        $category = ArticleCategory::factory()->create();
        $this->expectException(ValidationException::class);
        app(CategoryTreeService::class)->move($category, $category->id);
    }

    public function test_public_settings_do_not_accept_secret_keys(): void
    {
        $this->expectException(ValidationException::class);
        app(SetPublicSetting::class)->execute('smtp_password', 'synthetic-value');
    }

    public function test_upload_path_and_editor_content_are_sanitized(): void
    {
        foreach (['../.env', 'products/../../a.jpg', 'products/a.php', 'https://example.test/a.jpg'] as $path) {
            $this->assertTrue(Validator::make(['image' => $path], ['image' => [new PublicImagePath]])->fails());
        }
        $product = app(CreateProduct::class)->execute([
            'category_id' => Category::factory()->create()->id, 'name' => 'Safe text',
            'description' => '<p>Safe</p><script>alert(1)</script>',
        ]);
        $this->assertStringNotContainsString('<script', $product->description);
    }

    public function test_authorization_defaults_deny_and_uses_exment_identity(): void
    {
        $this->assertFalse(Gate::allows('catalog.manage'));
        $this->assertFalse((new StoreProductRequest)->authorize());
        $user = \Mockery::mock(LoginUser::class)->makePartial();
        $user->shouldReceive('isAdministrator')->andReturn(false);
        $this->assertFalse(Gate::forUser($user)->allows('catalog.manage'));
        $owner = \Mockery::mock(LoginUser::class)->makePartial();
        $owner->shouldReceive('isAdministrator')->andReturn(true);
        $this->assertTrue(Gate::forUser($owner)->allows('catalog.manage'));
        $hash = Hash::make('synthetic-password-for-test');
        $this->assertTrue(Hash::check('synthetic-password-for-test', $hash));
        $this->assertNotSame(32, strlen($hash));
    }
}
