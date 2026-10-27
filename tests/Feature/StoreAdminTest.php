<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Actions\Commerce\ChangeOrderStatus;
use App\Actions\Commerce\CreateOrder;
use App\Domain\Commerce\OrderStatus;
use App\Http\Middleware\AdminOperationLog;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Admin\CatalogEditor;
use App\Services\Admin\ProductImages;
use App\Services\Admin\RecordEditor;
use App\Services\Admin\StoreAccess;
use App\Services\Admin\StoreQueries;
use Exceedone\Exment\Model\Menu;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\RoleGroupUserOrganization;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Services\Login\LoginService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\ExmentAdminTestCase;

final class StoreAdminTest extends ExmentAdminTestCase
{
    private function product(array $options = []): Product
    {
        return app(CreateProduct::class)->execute(['category_id' => Category::factory()->create()->id,
            'name' => 'Admin synthetic plant', 'slug' => 'plant-'.fake()->unique()->numberBetween(1, 9999999),
            'status' => 'PUBLISHED', 'published_at' => now()->subDay(), 'options' => $options]);
    }

    private function order(): Order
    {
        $variant = app(CreateVariant::class)->execute($this->product(), ['sku' => 'SYN-'.fake()->unique()->numberBetween(1, 9999999),
            'pricing_type' => 'FIXED', 'price' => 99000, 'quantity' => 10]);

        return app(CreateOrder::class)->execute(['customer_name' => 'Synthetic Buyer', 'customer_phone' => '0900000000',
            'shipping_address' => ['line1' => 'Synthetic Address', 'country_code' => 'VN'],
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]]]);
    }

    public function test_guest_is_redirected_and_unassigned_user_cannot_read_or_write_domains(): void
    {
        $this->get($this->storeUrl('catalog', 'products'))->assertRedirect('/admin/auth/login');
        $this->loginAsRole();
        $this->get($this->storeUrl('dashboard'))->assertOk()->assertSee('chưa được cấp quyền');
        $this->getJson($this->storeUrl('catalog', 'products'))->assertForbidden();
        $this->postJson($this->storeUrl('catalog', 'products'), [])->assertForbidden();
    }

    public function test_product_manager_cannot_view_orders_customers_settings_or_native_user_management(): void
    {
        $this->loginAsRole('product_manager');
        $this->get($this->storeUrl('catalog', 'products'))->assertOk();
        foreach (['orders' => 'orders', 'customers' => 'customers', 'settings' => 'settings', 'content' => 'articles'] as $module => $entity) {
            $this->getJson($this->storeUrl($module, $entity))->assertForbidden();
            $this->postJson($this->storeUrl($module, $entity), [])->assertForbidden();
        }
        // Native permission middleware protects built-in identity and role endpoints too.
        foreach (['/admin/data/user', '/admin/role_group', '/admin/loginuser'] as $url) {
            $response = $this->get($url);
            $this->assertFalse($response->isSuccessful(), $url.' must reject a catalog-only user');
        }
    }

    public function test_role_revocation_is_immediate_even_when_system_permissions_are_disabled(): void
    {
        $user = $this->loginAsRole('product_manager');
        $this->assertTrue(app(StoreAccess::class)->allows($user, 'catalog'));
        RoleGroupUserOrganization::where('role_group_target_id', $user->getUserId())->delete();
        System::permission_available(0);
        $this->assertFalse(app(StoreAccess::class)->allows($user, 'catalog'));
    }

    public function test_content_and_order_role_presets_are_least_privilege(): void
    {
        $content = $this->loginAsRole('content_manager');
        $this->assertTrue(Gate::forUser($content)->allows('content.manage'));
        $this->assertFalse(Gate::forUser($content)->allows('catalog.manage'));
        $orders = $this->loginAsRole('order_manager');
        $this->assertTrue(Gate::forUser($orders)->allows('commerce.manage'));
        $this->assertTrue(Gate::forUser($orders)->allows('customers.manage'));
        $this->assertFalse(Gate::forUser($orders)->allows('settings.manage'));
    }

    public function test_admin_configuration_is_idempotent_and_keeps_existing_native_identity(): void
    {
        $user = $this->loginAsRole(admin: true);
        $hash = $user->password;
        $this->artisan('store:configure-admin')->assertSuccessful();
        $this->artisan('store:configure-admin')->assertSuccessful();
        $this->assertSame(6, Plugin::where('plugin_name', 'like', 'Bancay%')->count());
        $this->assertSame(6, Menu::where('menu_type', 'plugin')->where('menu_name', 'like', 'store_bancay_%')->count());
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_native_admin_pages_render_and_dashboard_hides_unauthorized_statistics(): void
    {
        $this->loginAsRole(admin: true);
        foreach (['catalog' => ['categories', 'products', 'inventory'], 'orders' => ['orders'], 'customers' => ['customers'],
            'content' => ['article-categories', 'articles', 'banners'], 'settings' => ['settings']] as $module => $entities) {
            foreach ($entities as $entity) {
                $this->get($this->storeUrl($module, $entity))->assertOk();
            }
        }
        $this->get($this->storeUrl('dashboard'))->assertOk()->assertSee('Tổng sản phẩm')->assertSee('Doanh thu');
        $this->loginAsRole('content_manager');
        $this->get($this->storeUrl('dashboard'))->assertOk()->assertSee('Bài viết')->assertDontSee('Tổng sản phẩm')->assertDontSee('Đơn mới');
    }

    public function test_category_form_validates_unique_slug_and_cycle_and_restricts_used_category(): void
    {
        $this->loginAsRole('product_manager');
        $url = $this->storeUrl('catalog', 'categories');
        $data = ['name' => 'Cây cảnh', 'slug' => 'cay-canh', 'status' => 'DRAFT', 'sort_order' => 0, 'featured' => 0];
        $this->post($url, $data)->assertRedirect();
        $category = Category::where('slug', 'cay-canh')->firstOrFail();
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->postJson($url, array_replace($data, ['slug' => 'outside-timestamp', 'status' => 'PUBLISHED', 'published_at' => '2099-01-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('published_at');
        $this->putJson($url.'/'.$category->id, $data + ['parent_id' => $category->id])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        Product::factory()->create(['category_id' => $category->id]);
        $this->deleteJson($url.'/'.$category->id, ['confirm' => 1])->assertUnprocessable();
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_product_form_sanitizes_html_ignores_legacy_identity_and_archives_without_losing_order_snapshots(): void
    {
        $this->loginAsRole('product_manager');
        $category = Category::factory()->create();
        $data = ['name' => 'Hoa hồng 🌱', 'category_id' => $category->id, 'status' => 'DRAFT', 'featured' => 1,
            'description' => '<p>Safe</p><script>alert(1)</script>', 'legacy_id' => 9191];
        $this->post($this->storeUrl('catalog', 'products'), $data)->assertRedirect();
        $product = Product::where('slug', 'hoa-hong')->firstOrFail();
        $this->assertNull($product->legacy_id);
        $this->assertStringNotContainsString('<script', $product->description);
        $this->get($this->storeUrl('catalog', 'products/'.$product->id))->assertOk()->assertSee('Hoa hồng');
        $this->delete($this->storeUrl('catalog', 'products/'.$product->id), ['confirm' => 1])->assertRedirect();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $order = $this->order();
        $product = Product::findOrFail($order->items[0]->product_id);
        app(RecordEditor::class)->delete('catalog', 'products', $product->id);
        $this->assertSame(99000, $order->fresh('items')->items[0]->unit_price);
        $this->assertDatabaseHas('inventories', ['product_variant_id' => $order->items[0]->product_variant_id, 'reserved_quantity' => 2]);
    }

    public function test_image_upload_reencodes_pixels_and_rejects_fake_image_and_cross_product_update(): void
    {
        Storage::fake('public');
        $this->loginAsRole('product_manager');
        $product = $this->product();
        $url = $this->storeUrl('catalog', 'products/'.$product->id.'/actions/');
        $this->post($url.'upload', ['images' => [UploadedFile::fake()->image('photo.png', 50, 50)]])->assertRedirect();
        $image = $product->images()->firstOrFail();
        Storage::disk('public')->assertExists($image->path);
        $this->assertStringEndsWith('.webp', $image->path);
        $this->assertTrue($image->is_primary);
        $bytes = Storage::disk('public')->get($image->path);
        $this->assertSame('WEBP', substr($bytes, 8, 4));
        $this->postJson($url.'upload', ['images' => [UploadedFile::fake()->createWithContent('bad.jpg', '<?php echo 1;')]])->assertUnprocessable();
        $this->postJson($url.'upload', ['images' => UploadedFile::fake()->image('single.png')])->assertUnprocessable();
        $other = $this->product()->images()->create(['path' => 'products/other.webp']);
        $this->postJson($url.'image', ['image_id' => $other->id, 'alt_text' => 'bad', 'sort_order' => 0, 'is_primary' => 1])->assertNotFound();
        $this->assertDatabaseCount('product_images', 2);
    }

    public function test_image_batch_failure_cleans_written_files_and_primary_switch_is_atomic(): void
    {
        Storage::fake('public');
        $product = $this->product();
        try {
            app(ProductImages::class)->upload($product, [UploadedFile::fake()->image('safe.png'), UploadedFile::fake()->createWithContent('bad.jpg', 'bad')]);
            $this->fail();
        } catch (ValidationException) {
            $this->assertCount(0, Storage::disk('public')->allFiles());
        }
        app(ProductImages::class)->upload($product, [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')]);
        $images = $product->images()->get();
        app(ProductImages::class)->update($product, $images[1]->id, ['sort_order' => 0, 'alt_text' => 'Second', 'is_primary' => 1]);
        $this->assertSame(1, $product->images()->where('is_primary', 1)->count());
        $this->assertTrue($images[1]->fresh()->is_primary);
        app(ProductImages::class)->update($product, $images[1]->id, ['sort_order' => 2, 'alt_text' => 'Same primary', 'is_primary' => 1]);
        $this->assertTrue($images[1]->fresh()->is_primary);
    }

    public function test_options_keep_ids_reject_duplicates_and_cannot_delete_used_values(): void
    {
        $product = $this->product();
        $editor = app(CatalogEditor::class);
        $option = $editor->option($product, ['name' => 'Trọng lượng', 'sort_order' => 0, 'values' => ['500g', '1kg']]);
        $id = $option->values[0]->id;
        $editor->option($product, ['name' => 'Weight', 'sort_order' => 1, 'values' => ['500g', '1kg', '2kg']], $option->id);
        $this->assertSame($id, $option->fresh('values')->values->firstWhere('value', '500g')->id);
        $editor->generate($product, ['sku_prefix' => 'GH', 'confirm' => 1]);
        try {
            $editor->option($product, ['name' => 'Weight', 'sort_order' => 1, 'values' => ['1kg']], $option->id);
            $this->fail();
        } catch (ValidationException) {
            $this->assertDatabaseHas('product_option_values', ['id' => $id]);
        }
        $this->expectException(ValidationException::class);
        $editor->option($product, ['name' => 'Weight', 'sort_order' => 1, 'values' => ['500g', '500G']], $option->id);
    }

    public function test_variant_generation_is_bounded_additive_and_does_not_overwrite_existing_stock_price_or_ids(): void
    {
        $product = $this->product([['name' => 'Weight', 'values' => ['500g', '1kg']], ['name' => 'Plant', 'values' => ['Rose', 'Orchid']]]);
        $editor = app(CatalogEditor::class);
        $this->assertSame(4, $editor->generate($product, ['sku_prefix' => 'GH', 'confirm' => 1]));
        $variant = $product->variants()->firstOrFail();
        $variant->update(['pricing_type' => 'FIXED', 'price' => 99000]);
        $before = $variant->fresh()->getAttributes();
        $this->assertSame(0, $editor->generate($product, ['sku_prefix' => 'OTHER', 'confirm' => 1]));
        $this->assertSame($before, $variant->fresh()->getAttributes());
        $this->assertDatabaseCount('inventories', 4);
        $large = $this->product([['name' => 'A', 'values' => array_map('strval', range(1, 17))], ['name' => 'B', 'values' => array_map('strval', range(1, 17))]]);
        $this->expectException(ValidationException::class);
        $editor->generate($large, ['sku_prefix' => 'BIG', 'confirm' => 1]);
    }

    public function test_variant_update_validates_price_unique_sku_and_keeps_combination_and_stock(): void
    {
        $product = $this->product();
        $variant = app(CreateVariant::class)->execute($product, ['sku' => 'ONE', 'pricing_type' => 'FIXED', 'price' => 99000, 'quantity' => 5]);
        $editor = app(CatalogEditor::class);
        $data = ['sku' => 'RENAMED', 'pricing_type' => 'FIXED', 'price' => 120000, 'sale_price' => 100000, 'status' => 'ACTIVE'];
        $editor->updateVariant($product, $variant->id, $data);
        $this->assertSame(5, $variant->inventory->quantity);
        $this->assertSame($variant->combination_key, $variant->fresh()->combination_key);
        $this->expectException(ValidationException::class);
        $editor->updateVariant($product, $variant->id, array_replace($data, ['sale_price' => 130000]));
    }

    public function test_inventory_rejects_negative_below_reserved_and_stale_updates(): void
    {
        $this->loginAsRole('product_manager');
        $order = $this->order();
        $stock = Inventory::firstOrFail();
        $url = $this->storeUrl('catalog', 'inventory/'.$stock->id.'/actions/adjust');
        $this->postJson($url, ['quantity' => -1, 'expected_quantity' => 10, 'reason' => 'Test'])->assertUnprocessable();
        $this->postJson($url, ['quantity' => 1, 'expected_quantity' => 10, 'reason' => 'Test'])->assertUnprocessable();
        $this->post($url, ['quantity' => 20, 'expected_quantity' => 10, 'reason' => 'Synthetic receipt'])->assertRedirect();
        $this->postJson($url, ['quantity' => 21, 'expected_quantity' => 10, 'reason' => 'Stale form'])->assertUnprocessable();
        $this->assertSame(20, $stock->fresh()->quantity);
        $this->assertSame(2, $stock->fresh()->reserved_quantity);
    }

    public function test_order_status_endpoint_records_actor_and_rejects_replay_and_completed_cancel(): void
    {
        $user = $this->loginAsRole('order_manager');
        $order = $this->order();
        $url = $this->storeUrl('orders', 'orders/'.$order->id.'/actions/status');
        $this->post($url, ['status' => 'CONFIRMED', 'expected_status' => 'NEW', 'note' => 'Synthetic confirmation'])->assertRedirect();
        $this->assertDatabaseHas('order_status_histories', ['order_id' => $order->id, 'changed_by' => $user->id, 'from_status' => 'NEW', 'to_status' => 'CONFIRMED']);
        $this->postJson($url, ['status' => 'PACKING', 'expected_status' => 'NEW'])->assertUnprocessable();
        $this->assertSame('CONFIRMED', $order->fresh()->status->value);
        foreach (['PACKING', 'SHIPPING', 'COMPLETED'] as $status) {
            app(ChangeOrderStatus::class)->execute($order, OrderStatus::from($status), $user->id);
        }
        $this->postJson($url, ['status' => 'CANCELLED', 'expected_status' => 'COMPLETED'])->assertUnprocessable();
        $this->get($this->storeUrl('orders', 'orders/'.$order->id))->assertOk()->assertSee('Snapshot')->assertSee('Synthetic confirmation');
        $this->assertDatabaseCount('order_status_histories', 5);
    }

    public function test_cancel_releases_stock_once_and_order_manager_cannot_generic_update_totals_or_items(): void
    {
        $this->loginAsRole('order_manager');
        $order = $this->order();
        $url = $this->storeUrl('orders', 'orders/'.$order->id);
        $this->putJson($url, ['total' => 1, 'status' => 'COMPLETED'])->assertStatus(405);
        $this->deleteJson($url, ['confirm' => 1])->assertStatus(405);
        $this->post($url.'/actions/status', ['status' => 'CANCELLED', 'expected_status' => 'NEW'])->assertRedirect();
        $this->postJson($url.'/actions/status', ['status' => 'CANCELLED', 'expected_status' => 'CANCELLED'])->assertUnprocessable();
        $this->assertDatabaseHas('inventories', ['quantity' => 10, 'reserved_quantity' => 0]);
        $this->assertSame(198000, $order->fresh()->total);
    }

    public function test_nested_product_ids_do_not_allow_cross_product_variant_or_option_mutation(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product();
        $other = $this->product([['name' => 'Size', 'values' => ['S']]]);
        $variant = app(CreateVariant::class)->execute($product, ['sku' => 'IDOR', 'pricing_type' => 'CONTACT', 'quantity' => 0]);
        $url = $this->storeUrl('catalog', 'products/'.$other->id.'/actions/variant');
        $this->postJson($url, ['variant_id' => $variant->id, 'sku' => 'ATTACK', 'pricing_type' => 'CONTACT', 'status' => 'ACTIVE'])->assertNotFound();
        $this->assertSame('IDOR', $variant->fresh()->sku);
        $this->putJson($this->storeUrl('catalog', 'products/0'), [])->assertNotFound();
        $this->get($this->storeUrl('catalog', 'products/0/edit'))->assertNotFound();
    }

    public function test_customer_addresses_validate_ownership_and_default_and_old_order_contact_is_unchanged(): void
    {
        $this->loginAsRole('order_manager');
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $order = $this->order();
        $order->update(['customer_id' => $customer->id]);
        $snapshot = $order->only(['customer_name', 'customer_phone', 'customer_email', 'shipping_address']);
        $address = $other->addresses()->create(['recipient_name' => 'Other', 'phone' => '0900000000', 'line1' => 'Other address', 'country_code' => 'VN']);
        $url = $this->storeUrl('customers', 'customers/'.$customer->id.'/actions/address');
        $data = ['recipient_name' => 'Synthetic', 'phone' => '0900000000', 'line1' => 'New address', 'country_code' => 'VN', 'is_default' => 1];
        $this->postJson($url, $data + ['address_id' => $address->id])->assertNotFound();
        $this->postJson($url, $data + ['postal_code' => str_repeat('1', 21)])->assertUnprocessable()->assertJsonValidationErrors('postal_code');
        $this->post($url, $data)->assertRedirect();
        $this->post($url, array_replace($data, ['line1' => 'Second']))->assertRedirect();
        $this->assertSame(1, $customer->addresses()->where('is_default', 1)->count());
        $current = $customer->addresses()->where('is_default', 1)->firstOrFail();
        $this->post($url, $data + ['address_id' => $current->id])->assertRedirect();
        $this->assertTrue($current->fresh()->is_default);
        $this->put($this->storeUrl('customers', 'customers/'.$customer->id), [
            'name' => 'Changed profile', 'phone' => '0900000028', 'email' => 'changed@example.test', 'status' => 'ACTIVE',
        ])->assertRedirect();
        $this->assertSame($snapshot, $order->fresh()->only(array_keys($snapshot)));
        $this->get($this->storeUrl('customers', 'customers/'.$customer->id))->assertOk()->assertSee('Lịch sử đơn hàng');
    }

    public function test_articles_banners_and_settings_validate_and_never_accept_secrets_or_scripts(): void
    {
        Storage::fake('public');
        $this->loginAsRole(admin: true);
        $category = ArticleCategory::factory()->create();
        $data = ['title' => 'Synthetic Article', 'article_category_id' => $category->id, 'status' => 'PUBLISHED', 'published_at' => now()->subMinute()->toDateTimeString(),
            'content' => '<p>Safe</p><img src=x onerror=alert(1)><script>alert(1)</script>', 'featured' => 0, 'show_in_menu' => 0];
        $this->post($this->storeUrl('content', 'articles'), $data)->assertRedirect();
        $article = Article::firstOrFail();
        $this->assertStringNotContainsString('onerror', $article->content);
        $this->assertStringNotContainsString('<script', $article->content);
        $banner = ['name' => 'Synthetic Banner', 'position' => 'home_slider', 'sort_order' => 0, 'priority' => 0, 'status' => 'ACTIVE', 'link' => 'javascript:alert(1)'];
        $this->postJson($this->storeUrl('content', 'banners'), $banner)->assertUnprocessable()->assertJsonValidationErrors(['link', 'image_upload']);
        $banner['link'] = 'https://example.test';
        $banner['image_upload'] = UploadedFile::fake()->image('banner.png');
        $this->post($this->storeUrl('content', 'banners'), $banner)->assertRedirect();
        $this->assertDatabaseCount('banners', 1);
        $this->postJson($this->storeUrl('settings', 'settings'), ['settings' => ['DB_PASSWORD' => 'bad']])->assertUnprocessable();
        $this->post($this->storeUrl('settings', 'settings'), ['settings' => ['site_name' => '<script>unsafe</script> Store', 'email' => 'contact@example.test']])->assertRedirect();
        $this->assertStringNotContainsString('<script', Setting::where('key', 'site_name')->value('value'));
    }

    public function test_search_filter_is_parameterized_and_product_list_query_count_stays_bounded(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product();
        app(CreateVariant::class)->execute($product, ['sku' => 'FIND-ME', 'pricing_type' => 'CONTACT', 'quantity' => 0]);
        $this->get($this->storeUrl('catalog', 'products').'?q=FIND-ME&status=PUBLISHED')->assertOk()->assertSee($product->name);
        $this->get($this->storeUrl('catalog', 'products').'?q=%27%20OR%201%3D1--')->assertOk()->assertDontSee($product->name);
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(StoreQueries::class)->listing('catalog', 'products', [])->paginate(25);
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        Product::factory()->count(20)->create(['category_id' => $product->category_id]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        app(StoreQueries::class)->listing('catalog', 'products', [])->paginate(25);
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($one + 1, $many);
        $this->assertLessThanOrEqual(8, $many);
    }

    public function test_export_rechecks_permission_and_neutralizes_spreadsheet_formulas(): void
    {
        $this->loginAsRole('order_manager');
        Customer::factory()->create(['name' => '=HYPERLINK("bad")', 'phone' => '+84900000000']);
        $response = $this->get($this->storeUrl('customers', 'customers/export'));
        $response->assertOk()->assertDownload('bancay-customers.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('password', $csv);
        $this->loginAsRole('content_manager');
        $this->getJson($this->storeUrl('customers', 'customers/export'))->assertForbidden();
    }

    public function test_date_filter_supports_a_single_bound_and_rejects_reversed_range(): void
    {
        $this->loginAsRole('order_manager');
        $order = $this->order();
        $url = $this->storeUrl('orders', 'orders');
        $this->get($url.'?to=2099-12-31')->assertOk()->assertSee($order->order_number);
        $this->getJson($url.'?from=2026-10-29&to=2026-10-28')->assertUnprocessable();
    }

    public function test_native_password_reset_hashes_credentials_and_audit_redacts_escaped_nested_secrets(): void
    {
        $user = $this->loginAsRole(admin: true);
        $password = Str::random(32).'"\\suffix';
        LoginService::resetPassword($user, ['password' => $password, 'send_password' => false, 'password_reset_flg' => true]);
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $this->assertTrue((bool) $user->fresh()->password_reset_flg);
        $log = new AdminOperationLog;
        $method = new \ReflectionMethod($log, 'hidePasswords');
        $masked = $method->invoke($log, json_encode(['password' => $password, 'nested' => ['password_confirmation' => $password], '_token' => 'synthetic-token']));
        $this->assertStringNotContainsString('suffix', $masked);
        $this->assertSame('***', json_decode($masked, true)['nested']['password_confirmation']);
    }

    public function test_removed_native_login_identity_revokes_existing_store_session(): void
    {
        $user = $this->loginAsRole('product_manager');
        $this->get($this->storeUrl('catalog', 'products'))->assertOk();
        $user->delete();
        $this->getJson($this->storeUrl('catalog', 'products'))->assertForbidden();
    }
}
