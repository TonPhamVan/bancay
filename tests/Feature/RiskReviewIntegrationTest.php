<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Tests\Support\ExmentAdminTestCase;

/** One continuous HTTP path across Exment adapters, domain services and public checkout. */
final class RiskReviewIntegrationTest extends ExmentAdminTestCase
{
    public function test_admin_catalog_to_guest_order_to_admin_snapshot_and_cancel(): void
    {
        $admin = $this->loginAsRole(admin: true);
        $published = now()->subMinute()->toDateTimeString();
        $this->post($this->storeUrl('catalog', 'categories'), ['name' => 'QA integration category', 'slug' => 'qa-integration-category',
            'status' => 'PUBLISHED', 'published_at' => $published, 'featured' => 1, 'sort_order' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $category = Category::where('slug', 'qa-integration-category')->firstOrFail();
        $this->post($this->storeUrl('catalog', 'products'), ['name' => 'QA integration plant', 'slug' => 'qa-integration-plant',
            'category_id' => $category->id, 'status' => 'PUBLISHED', 'published_at' => $published, 'featured' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $product = Product::where('slug', 'qa-integration-plant')->firstOrFail();
        $commands = $this->storeUrl('catalog', 'products/'.$product->id.'/actions/');
        foreach (['Cỡ' => "Nhỏ\nLớn", 'Chậu' => "Đất nung\nGốm"] as $name => $values) {
            $this->post($commands.'option', compact('name', 'values') + ['sort_order' => 0])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->post($commands.'generate', ['sku_prefix' => 'QA', 'confirm' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(4, $product->variants()->count());
        $variant = $product->variants()->firstOrFail();
        $this->post($commands.'variant', ['variant_id' => $variant->id, 'sku' => 'QA-RR-100', 'pricing_type' => 'FIXED',
            'price' => 100000, 'sale_price' => null, 'status' => 'ACTIVE'])->assertRedirect()->assertSessionHasNoErrors();
        $stockUrl = $this->storeUrl('catalog', 'inventory/'.$variant->inventory->id.'/actions/adjust');
        $this->post($stockUrl, ['quantity' => 10, 'expected_quantity' => 0, 'reason' => 'Synthetic review opening stock'])->assertRedirect()->assertSessionHasNoErrors();

        Auth::guard('admin')->logout();
        $this->assertGuest('admin');
        $this->get('/')->assertOk()->assertSee($product->name);
        $this->get('/danh-muc/'.$category->slug)->assertOk()->assertSee($product->name);
        $picker = $this->get('/san-pham/'.$product->slug)->assertOk()->viewData('picker');
        $this->assertCount(2, $picker['options']);
        $this->assertCount(1, $picker['variants']);
        $this->assertSame((string) $variant->id, $picker['variants'][0]['id']);
        $this->assertSame(100000, $picker['variants'][0]['price']['price']);
        $this->post('/gio-hang', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2,
            'stock' => 99999])->assertRedirect('/gio-hang')->assertSessionHasNoErrors();
        $this->get('/gio-hang')->assertOk()->assertSee('200.000 ₫');
        $token = $this->get('/thanh-toan')->assertOk()->viewData('token');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->post('/thanh-toan', ['checkout_token' => $token, 'customer_name' => 'QA synthetic guest', 'customer_phone' => '0900000000',
            'shipping_address' => ['line1' => 'Synthetic review address', 'country_code' => 'VN']])->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->get('/don-hang/'.$order->order_number)->assertOk()->assertSee('200.000 ₫');
        $this->assertSame(10, $variant->inventory->fresh()->quantity);
        $this->assertSame(2, $variant->inventory->fresh()->reserved_quantity);
        $this->assertSame('QA-RR-100', $order->items[0]->sku);
        $this->assertSame(2, $order->items[0]->quantity);
        $this->assertSame('NEW', $order->status->value);

        $this->actingAs($admin, 'admin');
        $this->post($commands.'variant', ['variant_id' => $variant->id, 'sku' => 'QA-RR-100', 'pricing_type' => 'FIXED',
            'price' => 150000, 'sale_price' => null, 'status' => 'ACTIVE'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(100000, $order->fresh('items')->items[0]->unit_price);
        $this->get($this->storeUrl('orders', 'orders/'.$order->id))->assertOk()->assertSee('QA-RR-100')->assertSee('100,000')->assertDontSee('150,000');
        $statusUrl = $this->storeUrl('orders', 'orders/'.$order->id.'/actions/status');
        $this->post($statusUrl, ['status' => 'CANCELLED', 'expected_status' => 'NEW', 'note' => 'Synthetic review cancellation'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(10, $variant->inventory->fresh()->quantity);
        $this->assertSame(0, $variant->inventory->fresh()->reserved_quantity);
        $this->assertCount(2, $order->fresh('statusHistories')->statusHistories);
        $this->assertSame((int) $admin->id, (int) $order->fresh('statusHistories')->statusHistories->last()->changed_by);
        $this->postJson($statusUrl, ['status' => 'CANCELLED', 'expected_status' => 'NEW'])->assertUnprocessable();
        $this->assertSame(10, $variant->inventory->fresh()->quantity);
        $this->assertSame(0, $variant->inventory->fresh()->reserved_quantity);
    }
}
