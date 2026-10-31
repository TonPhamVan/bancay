<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Actions\Commerce\CreateOrder;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\Admin\CatalogEditor;
use App\Services\Admin\ProductReadiness;
use App\Services\Admin\StoreQueries;
use App\Services\Storefront\Catalog;
use Tests\Support\ExmentAdminTestCase;

final class AdminImprovementsTest extends ExmentAdminTestCase
{
    private function product(array $options = []): Product
    {
        return app(CreateProduct::class)->execute(['category_id' => Category::factory()->create()->id,
            'name' => 'Synthetic B1 plant', 'slug' => 'b1-'.fake()->unique()->numberBetween(1, 999999),
            'status' => 'DRAFT', 'options' => $options]);
    }

    public function test_option_failure_keeps_only_the_submitted_group_and_escapes_input(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product([['name' => 'Kích thước', 'values' => ['Nhỏ', 'Lớn']], ['name' => 'Chậu', 'values' => ['Trắng', 'Nâu']]]);
        $group = $product->options()->firstOrFail();
        $url = $this->storeUrl('catalog', 'products/'.$product->id);
        $input = ['option_id' => $group->id, 'name' => '<script>Changed</script>', 'sort_order' => 7, 'values' => "Nhỏ\nNhỏ"];
        $this->from($url)->post($url.'/actions/option', $input)->assertRedirect($url)->assertSessionHasErrors('values.0');
        $response = $this->get($url)->assertOk()->assertSee('bị trùng')->assertSee('&lt;script&gt;Changed&lt;/script&gt;', false);
        $this->assertStringContainsString("Nhỏ\nNhỏ</textarea>", $response->getContent());
        $this->assertStringContainsString("Trắng\nNâu</textarea>", $response->getContent());
        $this->assertSame('Kích thước', $group->fresh()->name);
        $this->assertSame(['Nhỏ', 'Lớn'], $group->fresh('values')->values->pluck('value')->all());
        // Flash belongs to a product/group, not every form with the same field names.
        $other = $this->product([['name' => 'Other', 'values' => ['A']]]);
        $this->get($this->storeUrl('catalog', 'products/'.$other->id))->assertOk()->assertDontSee('Changed');
    }

    public function test_new_group_and_used_value_failure_keep_attempted_values_without_mutating_ids(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product();
        $url = $this->storeUrl('catalog', 'products/'.$product->id);
        $this->from($url)->post($url.'/actions/option', ['name' => 'Size', 'sort_order' => 0, 'values' => "S\nS"])
            ->assertRedirect($url)->assertSessionHasErrors();
        $this->assertStringContainsString("S\nS</textarea>", $this->get($url)->assertOk()->getContent());
        $this->assertSame(0, $product->options()->count());
        $group = app(CatalogEditor::class)->option($product, ['name' => 'Size', 'sort_order' => 0, 'values' => ['S', 'L']]);
        app(CatalogEditor::class)->generate($product, ['sku_prefix' => 'B1', 'confirm' => 1]);
        $ids = $group->values->pluck('id')->all();
        $this->from($url)->post($url.'/actions/option', ['option_id' => $group->id, 'name' => 'Size', 'sort_order' => 0, 'values' => 'L'])
            ->assertRedirect($url)->assertSessionHasErrors('values');
        $this->get($url)->assertOk()->assertSee('không được xóa hoặc đổi tên')->assertSee('>L</textarea>', false);
        $this->assertSame($ids, $group->fresh('values')->values->pluck('id')->all());
    }

    public function test_preview_uses_storefront_but_cannot_publish_or_checkout_and_requires_catalog_permission(): void
    {
        $product = $this->product();
        app(CreateVariant::class)->execute($product, ['sku' => 'PREVIEW-ACTIVE', 'pricing_type' => 'FIXED', 'price' => 100000, 'quantity' => 10, 'status' => 'ACTIVE']);
        app(CreateVariant::class)->execute($this->product(), ['sku' => 'UNRELATED', 'pricing_type' => 'CONTACT', 'quantity' => 0]);
        $url = $this->storeUrl('catalog', 'products/'.$product->id).'?preview=1';
        $this->get($url)->assertRedirect('/admin/auth/login');
        $this->loginAsRole('content_manager');
        $this->getJson($url)->assertForbidden();
        $this->loginAsRole('product_manager');
        $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Xem trước nội bộ')->assertSee('PREVIEW-ACTIVE')->assertDontSee('UNRELATED')->assertDontSee('Mua ngay')->assertDontSee('Thêm vào giỏ');
        $this->get('/san-pham/'.$product->slug.'?preview=1')->assertNotFound();
        $this->post('/gio-hang', ['product_id' => $product->id, 'variant_id' => $product->variants->first()->id, 'quantity' => 1])->assertSessionHasErrors();
        $this->assertSame('DRAFT', $product->fresh()->status->value);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checklist_and_dashboard_use_complete_category_publication_and_keep_contact_valid(): void
    {
        $this->loginAsRole('product_manager');
        $parent = Category::factory()->create(['status' => 'DRAFT']);
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $hidden = $this->product();
        $hidden->update(['category_id' => $child->id, 'status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
        $visible = $this->product();
        $visible->update(['status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
        app(CreateVariant::class)->execute($visible, ['sku' => 'CONTACT-OK', 'pricing_type' => 'CONTACT', 'quantity' => 0, 'status' => 'ACTIVE']);
        $check = app(ProductReadiness::class)->inspect($visible->fresh());
        $this->assertTrue($check['visible']);
        $this->assertFalse($check['buyable']);
        $this->assertTrue($check['checks'][4]['ok']);
        $this->assertFalse(app(ProductReadiness::class)->inspect($hidden->fresh())['visible']);
        app(Catalog::class)->reset();
        $stats = app(StoreQueries::class)->dashboard()['stats'];
        $this->assertSame(app(Catalog::class)->products()->count(), $stats['Sản phẩm hiển thị trên website']);
        $this->assertSame(1, $stats['Sản phẩm hiển thị trên website']);
        $this->get($this->storeUrl('catalog', 'products/'.$visible->id))->assertOk()->assertSee('Kiểm tra trước khi đăng bán')->assertSee('Liên hệ là lựa chọn hợp lệ');
    }

    public function test_inventory_filters_use_available_stock_and_csv_keeps_same_scope(): void
    {
        $this->loginAsRole('product_manager');
        foreach (['ZERO' => [0, 0], 'RESERVED' => [10, 10], 'LOW' => [10, 5], 'HIGH' => [10, 4]] as $sku => [$quantity, $reserved]) {
            $variant = app(CreateVariant::class)->execute($this->product(), ['sku' => $sku, 'pricing_type' => 'FIXED', 'price' => 100000, 'quantity' => $quantity]);
            $variant->inventory->update(['reserved_quantity' => $reserved]);
        }
        $query = app(StoreQueries::class);
        $this->assertSame(2, $query->listing('catalog', 'inventory', ['stock' => 'out'])->count());
        $this->assertSame(1, $query->listing('catalog', 'inventory', ['stock' => 'low'])->count());
        $this->assertSame(2, $query->listing('catalog', 'inventory', ['stock' => 'available'])->count());
        $this->get($this->storeUrl('catalog', 'inventory').'?stock=low')->assertOk()->assertSee('LOW')->assertDontSee('HIGH');
        $csv = $this->get($this->storeUrl('catalog', 'inventory/export').'?stock=out')->assertOk()->streamedContent();
        $this->assertStringContainsString('RESERVED', $csv);
        $this->assertStringNotContainsString('LOW', $csv);
        $this->getJson($this->storeUrl('catalog', 'inventory').'?stock=bogus')->assertUnprocessable();
    }

    public function test_cancel_requires_explicit_confirmation_and_releases_once_with_immutable_snapshot(): void
    {
        $this->loginAsRole('order_manager');
        $variant = app(CreateVariant::class)->execute($this->product(), ['sku' => 'CANCEL', 'pricing_type' => 'FIXED', 'price' => 100000, 'quantity' => 10]);
        $variant->product->update(['status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
        $order = app(CreateOrder::class)->execute(['customer_name' => 'Synthetic Buyer', 'customer_phone' => '0900000000',
            'shipping_address' => ['line1' => 'Synthetic Address', 'country_code' => 'VN'],
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]]]);
        $url = $this->storeUrl('orders', 'orders/'.$order->id);
        $this->get($url)->assertOk()->assertSee('Đơn có 2 sản phẩm')->assertSee('Tôi xác nhận hủy đơn');
        $this->postJson($url.'/actions/status', ['status' => 'CANCELLED', 'expected_status' => 'NEW'])->assertUnprocessable()->assertJsonValidationErrors('confirm_cancel');
        $this->assertSame(2, $variant->inventory->fresh()->reserved_quantity);
        $variant->update(['price' => 150000]);
        $input = ['status' => 'CANCELLED', 'expected_status' => 'NEW', 'confirm_cancel' => 1];
        $this->post($url.'/actions/status', $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson($url.'/actions/status', $input)->assertUnprocessable();
        $this->assertSame(0, $variant->inventory->fresh()->reserved_quantity);
        $this->assertSame(10, $variant->inventory->fresh()->quantity);
        $this->assertSame(100000, $order->items()->first()->unit_price);
        $this->assertSame(2, $order->statusHistories()->count());
    }

    public function test_form_labels_required_markers_and_field_errors_use_shop_vocabulary(): void
    {
        $this->loginAsRole('product_manager');
        $this->get($this->storeUrl('catalog', 'products/create'))->assertOk()->assertSee('Tên sản phẩm *')->assertSee('Tiêu đề SEO')->assertSee('Để trống để tạo từ tên');
        // Native Initialize uses require_once; simulate a fresh PHP request in this long-lived test process.
        require base_path('app/Exment/bootstrap.php');
        $this->get('/admin')->assertOk()->assertSee('aria-label="Tổng quan cửa hàng"', false);
        $this->postJson($this->storeUrl('catalog', 'products'), ['status' => 'DRAFT'])->assertUnprocessable()->assertJsonValidationErrors('name')->assertJsonPath('errors.name.0', 'Tên sản phẩm là bắt buộc.');
    }

    public function test_live_order_queue_and_origin_filters_do_not_mix_imported_history(): void
    {
        $this->loginAsRole('order_manager');
        $variant = app(CreateVariant::class)->execute($this->product(), ['sku' => 'QUEUE', 'pricing_type' => 'FIXED', 'price' => 100000, 'quantity' => 10]);
        $variant->product->update(['status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
        $data = ['customer_name' => 'Queue synthetic', 'customer_phone' => '0900000000',
            'shipping_address' => ['line1' => 'Synthetic Address', 'country_code' => 'VN'],
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]]];
        $live = app(CreateOrder::class)->execute($data);
        // A synthetic historical read fixture; this does not exercise or replay the importer.
        $history = app(CreateOrder::class)->execute($data);
        $history->update(['is_legacy' => true]);
        $queries = app(StoreQueries::class);
        $dashboard = $queries->dashboard();
        $this->assertSame(1, $dashboard['stats']['Đơn mới']);
        $this->assertSame([$live->id], $dashboard['recent']->pluck('id')->all());
        $this->assertSame([$history->id], $queries->listing('orders', 'orders', ['origin' => 'legacy'])->pluck('id')->all());
        $this->assertSame([$live->id], $queries->listing('orders', 'orders', ['origin' => 'live'])->pluck('id')->all());
        $this->get($this->storeUrl('orders', 'orders').'?origin=legacy')->assertOk()->assertSee('Lịch sử legacy · không xử lý tồn live')->assertDontSee($live->order_number);
        $this->assertSame(2, Order::count());
    }
}
