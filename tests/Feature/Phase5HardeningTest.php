<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Actions\Commerce\ChangeOrderStatus;
use App\Actions\Commerce\CreateOrder;
use App\Domain\Commerce\OrderStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class Phase5HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function variant()
    {
        $category = Category::factory()->create(['legacy_id' => 33]);
        $product = app(CreateProduct::class)->execute(['category_id' => $category->id, 'name' => 'Phase5 plant', 'slug' => 'phase5-plant',
            'status' => 'PUBLISHED', 'published_at' => now()->subMinute()->toDateTimeString()]);

        return app(CreateVariant::class)->execute($product, ['sku' => 'P5-SKU', 'status' => 'ACTIVE', 'pricing_type' => 'FIXED', 'price' => 100000, 'quantity' => 20]);
    }

    public function test_sitemap_matches_visible_ancestors_and_excludes_private_scheduled_drafts(): void
    {
        config(['app.url' => 'https://bancay.example.test']);
        $variant = $this->variant();
        $articleCategory = ArticleCategory::factory()->create();
        $article = Article::factory()->create(['article_category_id' => $articleCategory->id]);
        Article::factory()->create(['article_category_id' => $articleCategory->id, 'status' => 'DRAFT']);
        Article::factory()->create(['article_category_id' => $articleCategory->id, 'published_at' => now()->addDay()]);
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $document = simplexml_load_string($xml);
        $this->assertNotFalse($document);
        $urls = array_map('strval', $document->xpath('//*[local-name()="loc"]'));
        $this->assertContains('https://bancay.example.test/san-pham/'.$variant->product->slug, $urls);
        $this->assertContains('https://bancay.example.test/bai-viet/'.$article->slug, $urls);
        $this->assertCount(8, $urls); // four static, two categories, product and article
        $this->assertSame($urls, array_values(array_unique($urls)));
        foreach ($urls as $url) {
            $this->assertStringNotContainsString('/admin', $url);
            $this->assertStringNotContainsString('/thanh-toan', $url);
        }
        $variant->product->category->update(['status' => 'DRAFT']);
        $this->get('/sitemap.xml')->assertDontSee('/san-pham/'.$variant->product->slug);
    }

    public function test_robots_is_environment_specific_without_mutating_environment(): void
    {
        config(['storefront.indexable' => false]);
        $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        config(['storefront.indexable' => true, 'app.url' => 'https://bancay.example.test']);
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: https://bancay.example.test/sitemap.xml', false)->assertDontSee("Disallow: /\n", false);
    }

    public function test_security_headers_cover_public_native_and_error_responses(): void
    {
        foreach (['/', '/not-a-real-page', '/admin/auth/login'] as $path) {
            $this->get($path)->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Content-Security-Policy', "object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
        }
        $this->get('/not-a-real-page')->assertNotFound()->assertDontSee('/var/www')->assertDontSee('SQLSTATE');
    }

    public function test_legacy_category_pagination_translates_zero_based_without_open_redirect(): void
    {
        $variant = $this->variant();
        $category = $variant->product->category;
        $this->get('/old-dm33.html?page=1&next=https://evil.test&tracking=x')->assertRedirect('/danh-muc/'.$category->slug.'?page=2')->assertStatus(301);
        $this->get('/old-dm33.html?page=0')->assertRedirect('/danh-muc/'.$category->slug)->assertStatus(301);
        $this->getJson('/old-dm33.html?page=-1')->assertUnprocessable();
        $this->get('/removed-sp999999.html')->assertNotFound();
    }

    public function test_production_rejects_untrusted_host_headers(): void
    {
        config(['app.url' => 'https://bancay.example.test', 'app.debug' => false]);
        $this->app->instance('env', 'production');
        try {
            $this->get('https://untrusted.example.test/')->assertStatus(400)->assertDontSee('SQLSTATE');
            $this->get('https://bancay.example.test/')->assertOk();
        } finally {
            $this->app->instance('env', 'testing');
            Request::setTrustedHosts([]);
        }
    }

    public function test_production_error_body_hides_exception_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/phase5-test-error', function () {
            throw new \RuntimeException('Phase5 synthetic internal exception /var/www/private SQLSTATE');
        });
        $this->get('/phase5-test-error')->assertStatus(500)->assertDontSee('synthetic internal exception')->assertDontSee('/var/www/private')
            ->assertDontSee('SQLSTATE')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_banner_slots_obey_status_schedule_category_and_support_all_home_slides(): void
    {
        $variant = $this->variant();
        $category = $variant->product->category;
        foreach (['sidebar', 'content_bottom'] as $position) {
            Banner::factory()->create(['name' => 'Visible '.$position, 'position' => $position, 'category_id' => $category->id]);
            Banner::factory()->create(['name' => 'Hidden '.$position, 'position' => $position, 'category_id' => $category->id, 'status' => 'INACTIVE']);
            Banner::factory()->create(['name' => 'Future '.$position, 'position' => $position, 'starts_at' => now()->addDay()]);
            Banner::factory()->create(['name' => 'Wrong category '.$position, 'position' => $position, 'category_id' => Category::factory()->create()->id]);
        }
        foreach (['/danh-muc/'.$category->slug, '/san-pham/'.$variant->product->slug] as $path) {
            $this->get($path)->assertOk()->assertSee('Visible sidebar')->assertSee('Visible content_bottom')
                ->assertDontSee('Hidden sidebar')->assertDontSee('Future sidebar')->assertDontSee('Wrong category sidebar');
        }
        for ($i = 0; $i < 8; $i++) {
            Banner::factory()->create(['name' => 'Home slide '.$i, 'position' => 'home_slider', 'priority' => $i]);
        }
        $html = $this->get('/')->assertOk();
        foreach (range(0, 7) as $i) {
            $html->assertSee('Home slide '.$i);
        }
    }

    public function test_search_metacharacters_are_escaped_and_bad_shape_is_rejected(): void
    {
        foreach (['Cây cảnh', "' OR 1=1 --", '<script>alert(1)</script>', '" onfocus="alert(1)', '&lt;script&gt;', '%_', ''] as $q) {
            $this->get('/tim-kiem?'.http_build_query(['q' => $q]))->assertOk()->assertSee('noindex, nofollow')->assertDontSee('<script>alert(1)</script>', false);
        }
        $this->getJson('/tim-kiem?'.http_build_query(['q' => str_repeat('a', 121)]))->assertUnprocessable();
        $this->getJson('/tim-kiem?q[]=bad')->assertUnprocessable();
    }

    public function test_every_workflow_edge_and_rejection_preserves_stock_and_history(): void
    {
        $variant = $this->variant();
        foreach ([[OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Shipping, OrderStatus::Completed, OrderStatus::Returned],
            [OrderStatus::Cancelled], [OrderStatus::Confirmed, OrderStatus::Cancelled], [OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Cancelled],
            [OrderStatus::Confirmed, OrderStatus::Packing, OrderStatus::Shipping, OrderStatus::Returned]] as $path) {
            $before = $variant->inventory->fresh()->quantity;
            $order = app(CreateOrder::class)->execute(['customer_name' => 'Phase5 Synthetic', 'customer_phone' => '0900000000',
                'shipping_address' => ['line1' => 'Synthetic QA address', 'country_code' => 'VN'], 'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]]]);
            $count = 1;
            foreach ($path as $next) {
                $order = app(ChangeOrderStatus::class)->execute($order, $next);
                $this->assertSame(++$count, $order->statusHistories->count());
            }
            try {
                app(ChangeOrderStatus::class)->execute($order, OrderStatus::New);
                $this->fail('Terminal order must not reopen');
            } catch (ValidationException) {
                $this->assertSame($count, $order->statusHistories()->count());
            }
            $this->assertSame(0, $variant->inventory->fresh()->reserved_quantity);
            $this->assertSame($before - (in_array(OrderStatus::Shipping, $path, true) ? 1 : 0), $variant->inventory->fresh()->quantity);
        }
    }
}
