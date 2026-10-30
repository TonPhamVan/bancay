<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Commerce\CreateOrder;
use App\Models\Category;
use App\Models\Product;
use App\Services\Admin\CatalogEditor;
use App\Services\Admin\VariantMatrix;
use Illuminate\Support\Str;
use Tests\Support\ExmentAdminTestCase;

final class VariantMatrixTest extends ExmentAdminTestCase
{
    private function product(bool $grouped = true): Product
    {
        $product = app(CreateProduct::class)->execute([
            'name' => 'Matrix QA', 'slug' => 'matrix-'.Str::lower(Str::random(10)),
            'category_id' => Category::factory()->create(['status' => 'PUBLISHED', 'published_at' => now()->subDay()])->id,
            'status' => 'PUBLISHED', 'published_at' => now()->subDay(),
            'options' => $grouped ? [['name' => 'Size', 'values' => ['S', 'M']], ['name' => 'Color', 'values' => ['Red', 'Green']]] : [],
        ]);
        app(CatalogEditor::class)->generate($product, ['sku_prefix' => 'QA-'.Str::upper(Str::random(8)), 'confirm' => 1]);

        return $product->fresh();
    }

    private function payload(Product $product): array
    {
        return ['variants' => array_map(function ($row) {
            return array_merge(collect($row)->only(['id', 'version', 'sku', 'pricing_type', 'price', 'sale_price', 'image_id', 'status', 'quantity'])->all(), [
                'expected_quantity' => $row['quantity'], 'price' => $row['price'] === '' ? null : $row['price'],
                'sale_price' => $row['sale_price'] === '' ? null : $row['sale_price'], 'image_id' => $row['image_id'] === '' ? null : $row['image_id'],
            ]);
        }, app(VariantMatrix::class)->rows($product->fresh())), 'stock_reason' => null];
    }

    private function url(Product $product): string
    {
        return $this->storeUrl('catalog', "products/$product->id/actions/variant-matrix");
    }

    public function test_native_json_endpoint_saves_four_combinations_and_stock_in_one_batch(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product();
        $payload = $this->payload($product);
        foreach ($payload['variants'] as $i => &$row) {
            $row = array_merge($row, ['sku' => 'MATRIX-'.$i, 'pricing_type' => 'FIXED', 'price' => '100000', 'sale_price' => '90000', 'quantity' => '10', 'status' => 'ACTIVE']);
        }
        unset($row);
        $payload['stock_reason'] = 'QA opening stock';
        $response = $this->postJson($this->url($product), $payload)->assertOk()->assertJsonPath('updated', 4);
        $this->assertCount(4, $response->json('rows'));
        foreach ($product->fresh()->variants as $variant) {
            $this->assertSame(90000, $variant->effectivePrice());
            $this->assertSame(10, $variant->inventory->quantity);
            $this->assertSame(0, $variant->inventory->reserved_quantity);
        }
        $this->assertNotSame($payload['variants'][0]['version'], $response->json('rows.0.version'));
    }

    public function test_invalid_later_row_rolls_back_earlier_price_and_inventory(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product();
        $payload = $this->payload($product);
        $before = $product->variants()->first()->sku;
        $payload['variants'][0] = array_merge($payload['variants'][0], ['sku' => 'CHANGED-FIRST', 'pricing_type' => 'FIXED', 'price' => '100000', 'quantity' => 5]);
        $payload['variants'][1] = array_merge($payload['variants'][1], ['pricing_type' => 'FIXED', 'price' => '100000', 'sale_price' => '200000']);
        $payload['stock_reason'] = 'QA rollback';
        $this->postJson($this->url($product), $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.1.price');
        $first = $product->variants()->first();
        $this->assertSame($before, $first->sku);
        $this->assertNull($first->price);
        $this->assertSame(0, $first->inventory->quantity);
    }

    public function test_stale_sku_and_stock_versions_reject_without_overwriting(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product(false);
        $payload = $this->payload($product);
        $variant = $product->variants()->first();
        $variant->update(['sku' => 'NEWER-SKU']);
        $this->postJson($this->url($product), $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.0.version');
        $this->assertSame('NEWER-SKU', $variant->fresh()->sku);
        $payload = $this->payload($product);
        $variant->inventory->update(['quantity' => 1]);
        $this->postJson($this->url($product), $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.0.version');
        $this->assertSame(1, $variant->inventory->fresh()->quantity);
    }

    public function test_cross_product_variant_is_not_found_and_foreign_image_is_rejected(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product(false);
        $other = $this->product(false);
        $payload = $this->payload($other);
        $this->postJson($this->url($product), $payload)->assertNotFound();
        $image = $other->images()->create(['path' => 'products/qa.webp']);
        $payload = $this->payload($product);
        $payload['variants'][0]['image_id'] = $image->id;
        $this->postJson($this->url($product), $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.0.image_id');
        $this->assertNull($product->variants()->first()->image_id);
    }

    public function test_duplicate_sku_and_duplicate_rows_cannot_be_saved(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product();
        $payload = $this->payload($product);
        $payload['variants'][1]['sku'] = strtolower($payload['variants'][0]['sku']);
        $this->postJson($this->url($product), $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.1.sku');
        $payload = $this->payload($product);
        $payload['variants'][1] = $payload['variants'][0];
        $this->postJson($this->url($product), $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.1.id');
    }

    public function test_existing_global_sku_conflict_rolls_back_batch(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product();
        $other = $this->product(false);
        $payload = $this->payload($product);
        $payload['variants'][1]['sku'] = $other->variants()->first()->sku;
        $payload['variants'][0]['sku'] = 'ROLLBACK-UNIQUE';
        $this->postJson($this->url($product), $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.1.sku');
        $this->assertDatabaseMissing('product_variants', ['sku' => 'ROLLBACK-UNIQUE']);
    }

    public function test_stock_reason_required_and_reserved_stock_cannot_be_reduced(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product(false);
        $payload = $this->payload($product);
        $payload['variants'][0]['quantity'] = 5;
        $this->postJson($this->url($product), $payload)->assertUnprocessable()->assertJsonValidationErrors('stock_reason');
        $variant = $product->variants()->first();
        $variant->inventory->update(['quantity' => 10, 'reserved_quantity' => 2]);
        $payload = $this->payload($product);
        $payload['stock_reason'] = 'QA stock constraint';
        $payload['variants'][0]['quantity'] = 1;
        $this->postJson($this->url($product), $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.0.quantity');
        $this->assertSame(10, $variant->inventory->fresh()->quantity);
        $this->assertSame(2, $variant->inventory->fresh()->reserved_quantity);
    }

    public function test_contact_switch_clears_prices_and_money_remains_exact_at_php_limit(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product(false);
        $payload = $this->payload($product);
        $payload['variants'][0] = array_merge($payload['variants'][0], ['pricing_type' => 'FIXED', 'price' => (string) PHP_INT_MAX]);
        $this->postJson($this->url($product), $payload)->assertOk()->assertJsonPath('rows.0.price', (string) PHP_INT_MAX);
        $payload = $this->payload($product);
        $payload['variants'][0] = array_merge($payload['variants'][0], ['pricing_type' => 'CONTACT', 'price' => null, 'sale_price' => null]);
        $this->postJson($this->url($product), $payload)->assertOk()->assertJsonPath('rows.0.price', '');
        $this->assertNull($product->variants()->first()->price);
    }

    public function test_unknown_stock_fields_oversized_batch_and_missing_nullable_fields_fail_closed(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product(false);
        $payload = $this->payload($product);
        $payload['variants'][0]['reserved_quantity'] = 500;
        $this->postJson($this->url($product), $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.0');
        $payload = $this->payload($product);
        unset($payload['variants'][0]['price']);
        $this->postJson($this->url($product), $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.0.price');
        $payload = $this->payload($product);
        $payload['variants'] = array_fill(0, 257, $payload['variants'][0]);
        $this->postJson($this->url($product), $payload)->assertUnprocessable()->assertJsonValidationErrors('variants');
    }

    public function test_endpoint_rechecks_authorization_and_matrix_has_accessible_fields(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product();
        $this->get($this->storeUrl('catalog', 'products/'.$product->id))->assertOk()
            ->assertSee('data-variant-matrix', false)->assertSee('Bảng phân loại hàng')->assertSee('Lý do điều chỉnh tồn kho');
        $payload = $this->payload($product);
        $this->loginAsRole('content_manager');
        $this->postJson($this->url($product), $payload)->assertForbidden();
        $this->loginAsRole();
        $this->postJson($this->url($product), $payload)->assertForbidden();
    }

    public function test_matrix_repricing_preserves_existing_order_snapshot_and_reservation(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product(false);
        $variant = $product->variants()->first();
        $variant->update(['pricing_type' => 'FIXED', 'price' => 100000, 'status' => 'ACTIVE']);
        $variant->inventory->update(['quantity' => 10]);
        $order = app(CreateOrder::class)->execute(['customer_name' => 'QA buyer', 'customer_phone' => '0900000000',
            'shipping_address' => ['line1' => 'Synthetic QA address', 'country_code' => 'VN'],
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]]]);
        $payload = $this->payload($product);
        $payload['variants'][0]['price'] = '150000';
        $this->postJson($this->url($product), $payload)->assertOk();
        $this->assertSame(100000, $order->items()->first()->unit_price);
        $this->assertSame(200000, $order->fresh()->total);
        $this->assertSame(2, $variant->inventory->fresh()->reserved_quantity);
    }
}
