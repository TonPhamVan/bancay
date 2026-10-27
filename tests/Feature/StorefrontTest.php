<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Category;
use App\Models\CheckoutAttempt;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\Storefront\Checkout;
use App\Services\Storefront\Presentation;
use Database\Seeders\StorefrontDemoSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $overrides = [], array $options = []): Product
    {
        return app(CreateProduct::class)->execute(array_merge([
            'name' => 'Cây kiểm thử tổng hợp', 'slug' => 'plant-'.fake()->unique()->numberBetween(100000, 999999),
            'category_id' => Category::factory()->create()->id, 'status' => 'PUBLISHED',
            'published_at' => now()->subMinute()->toDateTimeString(), 'options' => $options,
        ], $overrides));
    }

    private function variant(?Product $product = null, array $overrides = []): ProductVariant
    {
        return app(CreateVariant::class)->execute($product ?? $this->product(), array_merge([
            'sku' => 'PUBLIC-'.fake()->unique()->numberBetween(100000, 999999), 'pricing_type' => 'FIXED',
            'price' => 120000, 'sale_price' => 99000, 'quantity' => 10,
        ], $overrides));
    }

    private function add(ProductVariant $variant, int $quantity = 2)
    {
        return $this->post('/gio-hang', ['product_id' => $variant->product_id, 'variant_id' => $variant->id, 'quantity' => $quantity]);
    }

    private function input(string $token): array
    {
        return ['checkout_token' => $token, 'customer_name' => 'Phase 3 Synthetic Guest', 'customer_phone' => '+84999999003',
            'customer_email' => 'phase3@example.test', 'shipping_address' => ['line1' => 'Synthetic test address', 'country_code' => 'VN']];
    }

    private function token(): string
    {
        $token = $this->get('/thanh-toan')->assertOk()->viewData('token');
        // Laravel's test client does not automatically carry response session cookies across requests.
        $this->withCookie(config('session.cookie'), session()->getId());

        return $token;
    }

    public function test_public_pages_have_empty_states_and_private_cart_headers(): void
    {
        foreach (['/', '/san-pham', '/tim-kiem?q=missing', '/bai-viet', '/lien-he', '/gio-hang'] as $path) {
            $this->get($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('lang="vi"', false);
        }
        $this->get('/gio-hang')->assertSee('Giỏ hàng đang đợi')->assertSee('noindex, nofollow');
        $this->get('/thanh-toan')->assertRedirect('/gio-hang');
    }

    public function test_frontend_demo_is_repeatable_and_refuses_production(): void
    {
        Storage::fake('public');
        $this->seed(StorefrontDemoSeeder::class);
        $this->seed(StorefrontDemoSeeder::class);
        $this->assertDatabaseCount('products', 4);
        $this->assertDatabaseCount('product_variants', 16);
        $this->assertDatabaseCount('product_images', 6);
        $this->assertDatabaseCount('articles', 3);
        $this->get('/')->assertOk()->assertDontSee('/storefront/fallback.svg');
        $this->app->instance('env', 'production');
        try {
            (new StorefrontDemoSeeder)->run();
            $this->fail('Demo seeder must reject production');
        } catch (\LogicException $e) {
            $this->assertSame('Storefront demo is local/testing only.', $e->getMessage());
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_only_published_past_products_and_visible_ancestors_are_public(): void
    {
        $parent = Category::factory()->create(['status' => 'DRAFT']);
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        foreach ([['status' => 'DRAFT'], ['published_at' => now()->addDay()->toDateTimeString()], ['category_id' => $child->id]] as $attributes) {
            $product = $this->product($attributes);
            $this->get('/san-pham/'.$product->slug)->assertNotFound();
            $this->get('/san-pham')->assertDontSee($product->slug);
        }
        $this->get('/danh-muc/'.$child->slug)->assertNotFound();
        $parent->update(['status' => 'PUBLISHED']);
        $this->get('/danh-muc/'.$child->slug)->assertOk();
        $parent->update(['published_at' => now()->addDay()]);
        $this->get('/danh-muc/'.$child->slug)->assertNotFound();
    }

    public function test_category_includes_all_visible_descendants_and_excludes_other_branches(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $grandchild = Category::factory()->create(['parent_id' => $child->id]);
        $inside = $this->product(['category_id' => $grandchild->id]);
        $outside = $this->product();
        $this->get('/danh-muc/'.$root->slug)->assertOk()->assertSee($inside->slug)->assertDontSee($outside->slug)
            ->assertSee('BreadcrumbList')->assertSee($child->name);
    }

    public function test_listing_paginates_at_24_and_validates_sort_and_page(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(26)->create(['category_id' => $category->id, 'status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
        $page = $this->get('/san-pham')->assertOk()->viewData('products');
        $this->assertCount(24, $page);
        $this->assertSame(26, $page->total());
        $this->assertCount(2, $this->get('/san-pham?page=2')->viewData('products'));
        $this->getJson('/san-pham?sort=price_desc%3Bdrop+table+orders')->assertUnprocessable();
        $this->getJson('/san-pham?page=-1')->assertUnprocessable();
    }

    public function test_search_name_and_active_sku_are_bound_and_xss_escaped(): void
    {
        $product = $this->product(['name' => 'Monstera thử nghiệm']);
        $variant = $this->variant($product, ['sku' => 'SEARCH-UNIQUE']);
        $this->get('/tim-kiem?q=Monstera')->assertOk()->assertSee($product->slug);
        $this->get('/tim-kiem?q=SEARCH-UNIQUE')->assertOk()->assertSee($product->slug);
        $variant->update(['status' => 'INACTIVE']);
        $this->get('/tim-kiem?q=SEARCH-UNIQUE')->assertOk()->assertDontSee($product->slug);
        $this->get('/tim-kiem?q='.urlencode("' OR 1=1 --"))->assertOk()->assertDontSee($product->slug);
        $this->get('/tim-kiem?q='.urlencode('<script>alert(1)</script>'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->get('/tim-kiem?q=%25')->assertOk()->assertDontSee($product->slug);
        $this->getJson('/tim-kiem?q='.str_repeat('x', 121))->assertUnprocessable();
    }

    public function test_sort_uses_current_fixed_variant_price_and_contact_sorts_last(): void
    {
        $high = $this->variant(null, ['price' => 200000, 'sale_price' => null]);
        $low = $this->variant(null, ['price' => 150000, 'sale_price' => 80000]);
        $contact = $this->variant(null, ['pricing_type' => 'CONTACT', 'price' => null, 'sale_price' => null]);
        $this->assertSame([$low->product_id, $high->product_id, $contact->product_id], $this->get('/san-pham?sort=price_asc')->viewData('products')->pluck('id')->all());
        $this->assertSame([$high->product_id, $low->product_id, $contact->product_id], $this->get('/san-pham?sort=price_desc')->viewData('products')->pluck('id')->all());
    }

    public function test_banner_status_schedule_position_and_category_scope_are_respected(): void
    {
        Banner::factory()->create(['name' => 'CURRENT HERO', 'image' => 'banners/current.webp']);
        foreach ([['status' => 'INACTIVE'], ['starts_at' => now()->addHour()], ['ends_at' => now()->subHour()],
            ['position' => 'sidebar'], ['category_id' => Category::factory()->create()->id]] as $override) {
            Banner::factory()->create(array_merge(['name' => 'HIDDEN BANNER'], $override));
        }
        $this->get('/')->assertOk()->assertSee('CURRENT HERO')->assertDontSee('HIDDEN BANNER');
    }

    public function test_product_picker_has_only_active_variants_with_stock_price_and_image(): void
    {
        $product = $this->product([], [['name' => 'Cỡ', 'values' => ['S', 'M']]]);
        $active = $this->variant($product, ['option_value_ids' => [$product->options[0]->values[0]->id]]);
        $this->variant($product, ['status' => 'INACTIVE', 'option_value_ids' => [$product->options[0]->values[1]->id]]);
        $active->inventory->update(['reserved_quantity' => 3]);
        $response = $this->get('/san-pham/'.$product->slug)->assertOk();
        $picker = $response->viewData('picker');
        $this->assertCount(1, $picker['variants']);
        $this->assertSame((string) $active->id, $picker['variants'][0]['id']);
        $this->assertSame(7, $picker['variants'][0]['available']);
        $this->assertSame('99.000 ₫', $picker['variants'][0]['price']['formatted']);
    }

    public function test_contact_product_structured_data_has_no_fake_offer_or_rating(): void
    {
        $variant = $this->variant(null, ['pricing_type' => 'CONTACT', 'price' => null, 'sale_price' => null]);
        $seo = $this->get('/san-pham/'.$variant->product->slug)->assertOk()->viewData('seo');
        $product = collect($seo['structured'])->firstWhere('@type', 'Product');
        $this->assertArrayNotHasKey('offers', $product);
        $this->assertArrayNotHasKey('aggregateRating', $product);
    }

    public function test_cart_add_merge_update_remove_clear_and_buy_now_use_same_service(): void
    {
        $a = $this->variant();
        $b = $this->variant();
        $this->add($a, 2)->assertRedirect('/gio-hang');
        $this->add($a, 1)->assertRedirect('/gio-hang');
        $this->assertSame(3, session('store.cart.'.$a->id.'.quantity'));
        $this->patch('/gio-hang/'.$a->id, ['quantity' => 4])->assertRedirect('/gio-hang');
        $this->get('/gio-hang')->assertSee('396.000 ₫')->assertSee($a->sku);
        $this->post('/gio-hang', ['product_id' => $b->product_id, 'variant_id' => $b->id, 'quantity' => 1, 'intent' => 'buy_now'])->assertRedirect('/thanh-toan');
        $this->delete('/gio-hang/'.$a->id)->assertRedirect('/gio-hang');
        $this->assertNull(session('store.cart.'.$a->id));
        $this->delete('/gio-hang')->assertRedirect('/gio-hang');
        $this->assertNull(session('store.cart'));
    }

    public function test_cart_rejects_price_tampering_mismatched_owner_and_invalid_quantities(): void
    {
        $variant = $this->variant();
        $other = $this->product();
        foreach ([['price' => 1], ['unit_price' => 1], ['quantity' => 0], ['quantity' => -1], ['quantity' => 1.2],
            ['quantity' => 1000001], ['product_id' => $other->id], ['variant_id' => 999999]] as $override) {
            $this->postJson('/gio-hang', array_merge(['product_id' => $variant->product_id, 'variant_id' => $variant->id, 'quantity' => 1], $override))->assertUnprocessable();
        }
        $this->assertEmpty(session('store.cart'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_draft_future_contact_and_out_of_stock_variants_cannot_be_added(): void
    {
        $variants = [$this->variant(null, ['status' => 'INACTIVE']), $this->variant(null, ['quantity' => 0]),
            $this->variant(null, ['pricing_type' => 'CONTACT', 'price' => null, 'sale_price' => null]),
            $this->variant($this->product(['status' => 'DRAFT'])), $this->variant($this->product(['published_at' => now()->addDay()->toDateTimeString()]))];
        foreach ($variants as $variant) {
            $this->add($variant, 1)->assertSessionHasErrors();
        }
        $this->assertEmpty(session('store.cart'));
    }

    public function test_cart_reprices_and_blocks_items_hidden_or_reserved_after_add(): void
    {
        $variant = $this->variant();
        $this->add($variant, 2);
        $variant->update(['sale_price' => 88000]);
        $this->get('/gio-hang')->assertSee('176.000 ₫');
        $variant->inventory->update(['reserved_quantity' => 9]);
        $this->get('/gio-hang')->assertSee('Tồn kho đã thay đổi')->assertDontSee('Tiến hành đặt hàng');
        $variant->product->update(['status' => 'DRAFT']);
        $this->get('/gio-hang')->assertSee('không còn khả dụng')->assertDontSee($variant->sku);
    }

    public function test_checkout_validation_rejects_client_items_totals_and_missing_contact_data(): void
    {
        $variant = $this->variant();
        $this->add($variant);
        $input = $this->input($this->token());
        foreach ([['total' => 1], ['items' => [['product_variant_id' => $variant->id, 'quantity' => 1]]],
            ['customer_name' => ''], ['customer_phone' => 'abc'], ['customer_email' => "x@example.test\r\nBcc: x"],
            ['shipping_address' => ['line1' => 'x', 'country_code' => 'VN', 'admin' => true]]] as $override) {
            $this->postJson('/thanh-toan', array_replace($input, $override))->assertUnprocessable();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $variant->inventory->fresh()->reserved_quantity);
    }

    public function test_guest_checkout_creates_authoritative_immutable_snapshots_and_owned_success(): void
    {
        $variant = $this->variant();
        $this->add($variant, 2);
        $input = $this->input($this->token());
        $response = $this->post('/thanh-toan', $input)->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $response->assertRedirect('/don-hang/'.$order->order_number);
        $this->assertSame(198000, $order->total);
        $this->assertSame('NEW', $order->status->value);
        $this->assertNull($order->customer_id);
        $this->assertSame(2, $variant->inventory->fresh()->reserved_quantity);
        $this->assertSame(10, $variant->inventory->fresh()->quantity);
        $this->assertCount(1, $order->statusHistories);
        $variant->product->update(['name' => 'Renamed later']);
        $variant->update(['price' => 200000, 'sale_price' => null]);
        $this->get('/don-hang/'.$order->order_number)->assertOk()->assertSee('198.000 ₫')->assertSee('Cây kiểm thử tổng hợp')->assertDontSee('Renamed later');
        $this->assertNull(session('store.cart'));
        $this->assertSame($order->id, CheckoutAttempt::firstOrFail()->order_id);
    }

    public function test_repeated_post_and_lost_session_grant_repair_do_not_create_duplicate_order(): void
    {
        $variant = $this->variant();
        $this->add($variant, 2);
        $input = $this->input($this->token());
        $this->post('/thanh-toan', $input)->assertRedirect();
        session()->forget('store.orders');
        session()->save();
        $this->post('/thanh-toan', $input)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, $variant->inventory->fresh()->reserved_quantity);
        $order = Order::firstOrFail();
        $this->get('/don-hang/'.$order->order_number)->assertOk();
    }

    public function test_token_is_bound_to_session_and_unknown_or_expired_token_fails(): void
    {
        $variant = $this->variant();
        $this->add($variant);
        $token = $this->token();
        $this->postJson('/thanh-toan', $this->input(str_repeat('a', 64)))->assertUnprocessable();
        CheckoutAttempt::query()->update(['owner_hash' => str_repeat('0', 64)]);
        $this->postJson('/thanh-toan', $this->input($token))->assertUnprocessable();
        CheckoutAttempt::query()->update(['owner_hash' => hash('sha256', session()->getId()), 'created_at' => now()->subMinutes(31)]);
        $this->postJson('/thanh-toan', $this->input($token))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_last_available_item_clears_cart_and_old_replay_keeps_a_new_cart(): void
    {
        $variant = $this->variant(null, ['quantity' => 1]);
        $this->add($variant, 1);
        $input = $this->input($this->token());
        $this->post('/thanh-toan', $input)->assertSessionHasNoErrors();
        $this->assertNull(session('store.cart'));
        $this->assertSame(1, $variant->inventory->fresh()->reserved_quantity);
        $other = $this->variant();
        $this->add($other, 1);
        $this->post('/thanh-toan', $input)->assertSessionHasNoErrors();
        $this->assertSame(1, session('store.cart.'.$other->id.'.quantity'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_quote_change_requires_review_and_refresh_reissues_token(): void
    {
        $variant = $this->variant();
        $this->add($variant);
        $old = $this->token();
        $variant->update(['sale_price' => 90000]);
        $this->postJson('/thanh-toan', $this->input($old))->assertUnprocessable();
        $new = $this->token();
        $this->assertNotSame($old, $new);
        $this->post('/thanh-toan', $this->input($new))->assertRedirect();
        $this->assertSame(180000, Order::firstOrFail()->total);
    }

    public function test_stock_or_category_change_after_checkout_prevents_order_creation(): void
    {
        $variant = $this->variant();
        $this->add($variant);
        $token = $this->token();
        $variant->inventory->update(['reserved_quantity' => 9]);
        $this->postJson('/thanh-toan', $this->input($token))->assertUnprocessable();
        $variant->inventory->update(['reserved_quantity' => 0]);
        $variant->product->category->update(['status' => 'DRAFT']);
        $this->postJson('/thanh-toan', $this->input($token))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $variant->inventory->fresh()->reserved_quantity);
    }

    public function test_failure_after_reservation_rolls_back_order_attempt_and_inventory_atomically(): void
    {
        $variant = $this->variant();
        $this->add($variant);
        $token = $this->token();
        Event::listen(QueryExecuted::class, function ($event) {
            if (str_starts_with($event->sql, 'insert into `orders`')) {
                throw new \RuntimeException('Synthetic transaction failure');
            }
        });
        try {
            app(Checkout::class)->place(session()->driver(), $this->input($token));
            $this->fail('Expected injected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic transaction failure', $e->getMessage());
        } finally {
            Event::forget(QueryExecuted::class);
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertNull(CheckoutAttempt::firstOrFail()->order_id);
        $this->assertSame(0, $variant->inventory->fresh()->reserved_quantity);
    }

    public function test_order_success_cannot_be_read_without_exact_session_grant(): void
    {
        $variant = $this->variant();
        $this->add($variant);
        $this->post('/thanh-toan', $this->input($this->token()));
        $order = Order::firstOrFail();
        session()->forget('store.orders');
        session()->save();
        $this->get('/don-hang/'.$order->order_number)->assertNotFound()->assertDontSee('Phase 3 Synthetic Guest');
        $this->withSession(['store.orders' => ['wrong-number' => $order->id]])->get('/don-hang/wrong-number')->assertNotFound();
        $this->get('/don-hang/'.$order->id)->assertNotFound();
    }

    public function test_articles_require_published_category_ancestors_and_sanitize_html(): void
    {
        $category = ArticleCategory::factory()->create(['show_in_menu' => true]);
        $article = Article::factory()->create(['article_category_id' => $category->id, 'content' => '<p>Safe prose</p><script>alert(1)</script><img src=x onerror=alert(1)>']);
        $this->get('/bai-viet/'.$article->slug)->assertOk()->assertSee('Safe prose')->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('onerror', false);
        $this->get('/chuyen-muc/'.$category->slug)->assertOk()->assertSee($article->slug);
        $category->update(['status' => 'DRAFT']);
        $this->get('/bai-viet/'.$article->slug)->assertNotFound();
        $category->update(['status' => 'PUBLISHED']);
        $article->update(['published_at' => now()->addDay()]);
        $this->get('/bai-viet/'.$article->slug)->assertNotFound();
    }

    public function test_seo_metadata_uses_trusted_origin_and_jsonld_escapes_script_delimiters(): void
    {
        config(['app.url' => 'https://store.example.test', 'storefront.indexable' => true]);
        $product = $this->product(['name' => '</script><script>alert(7)</script>', 'meta_title' => 'Explicit SEO title']);
        $this->variant($product);
        $response = $this->withServerVariables(['HTTP_HOST' => 'untrusted.example'])->get('/san-pham/'.$product->slug)->assertOk();
        $response->assertSee('<title>Explicit SEO title</title>', false)->assertSee('https://store.example.test/san-pham/'.$product->slug)
            ->assertDontSee('</script><script>alert(7)</script>', false)->assertSee('index, follow');
        $data = collect($response->viewData('seo')['structured'])->firstWhere('@type', 'Product');
        $this->assertSame('99000', $data['offers']['lowPrice']);
        $this->assertSame('VND', $data['offers']['priceCurrency']);
    }

    public function test_settings_are_text_and_unsafe_links_and_missing_paths_have_safe_fallbacks(): void
    {
        Setting::create(['key' => 'address', 'value' => '<script>address()</script>']);
        Setting::create(['key' => 'facebook', 'value' => 'javascript:alert(8)']);
        Setting::create(['key' => 'map', 'value' => 'file:///etc/passwd']);
        $this->get('/lien-he')->assertOk()->assertSee('&lt;script&gt;address()', false)->assertDontSee('javascript:alert(8)', false)->assertDontSee('file:///etc/passwd', false);
        Storage::fake('public');
        $this->assertSame('/storefront/fallback.svg', app(Presentation::class)->image('../../.env'));
        $this->assertSame('/storefront/fallback.svg', app(Presentation::class)->image('products/not-found.webp'));
    }

    public function test_legacy_redirects_resolve_public_legacy_ids_without_open_redirect_or_home_fallback(): void
    {
        $product = $this->product();
        $product->update(['legacy_id' => 123]);
        $category = $product->category;
        $category->update(['legacy_id' => 45]);
        $article = Article::factory()->create(['legacy_id' => 67]);
        $article->category->update(['legacy_id' => 89]);
        foreach (['sp123' => '/san-pham/'.$product->slug, 'dm45' => '/danh-muc/'.$category->slug,
            'bv67' => '/bai-viet/'.$article->slug, 'cm89' => '/chuyen-muc/'.$article->category->slug] as $key => $target) {
            $this->get('/old-'.$key.'.html?next=https://evil.example')->assertStatus(301)->assertRedirect($target);
        }
        $product->update(['status' => 'DRAFT']);
        $this->get('/old-sp123.html')->assertNotFound();
        $this->get('/old-sp999.html')->assertNotFound();
        $this->get('/tat-ca-san-pham?keyword=Monstera&page=1')->assertStatus(301)->assertRedirect('/tim-kiem?q=Monstera&page=2');
    }

    public function test_public_csrf_is_not_exempt_and_wrong_http_method_is_rejected(): void
    {
        $this->app->instance('env', 'local');
        try {
            $this->post('/gio-hang', [])->assertStatus(419);
            $this->post('/thanh-toan', [])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->put('/gio-hang')->assertStatus(405);
        $this->get('/missing-product-sp999999.html')->assertNotFound();
    }

    public function test_listing_and_cart_query_counts_remain_bounded_when_items_grow(): void
    {
        $a = $this->variant();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/san-pham')->assertOk();
        $small = count(DB::getQueryLog());
        $stored = [$a->id => ['product_id' => $a->product_id, 'quantity' => 1]];
        for ($i = 0; $i < 20; $i++) {
            $v = $this->variant();
            $stored[$v->id] = ['product_id' => $v->product_id, 'quantity' => 1];
        }
        DB::flushQueryLog();
        $this->get('/san-pham')->assertOk();
        $large = count(DB::getQueryLog());
        $this->assertLessThanOrEqual($small + 2, $large);
        DB::flushQueryLog();
        $this->withSession(['store.cart' => $stored])->get('/gio-hang')->assertOk();
        $cartQueries = count(DB::getQueryLog());
        $this->assertLessThan(18, $cartQueries);
        DB::disableQueryLog();
        if (getenv('PHASE3_COLLECT_EVIDENCE') === '1') {
            file_put_contents(base_path('docs/phase-3/evidence/performance.json'), json_encode([
                'database' => 'bancay_test', 'products_1_queries' => $small, 'products_21_queries' => $large,
                'cart_21_lines_queries' => $cartQueries, 'method' => 'Real HTTP kernel + Blade render, real MariaDB, no mocked queries',
                'limits' => 'Small synthetic fixtures; not a production load or latency benchmark.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
        }
    }
}
