<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Actions\Commerce\CreateOrder;
use App\Admin\StoreLabels;
use App\Admin\StoreResources;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\Admin\CatalogEditor;
use App\Services\Admin\OrderQueue;
use App\Services\Admin\ProductImages;
use App\Services\Admin\SeoSnippet;
use App\Services\Storefront\Catalog;
use App\Services\Storefront\Seo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\ExmentAdminTestCase;

final class AdminUxIterationTest extends ExmentAdminTestCase
{
    public function test_overflow_option_retry_preserves_all_input_marks_unsaved_and_uses_localized_limit(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product();
        $group = app(CatalogEditor::class)->option($product, ['name' => 'Size', 'sort_order' => 0, 'values' => ['S', 'L']]);
        $ids = $group->values->pluck('id')->all();
        $url = $this->storeUrl('catalog', 'products/'.$product->id);
        $values = implode("\n", array_map(fn ($n) => 'Value '.$n, range(1, 101)));
        $this->from($url)->post($url.'/actions/option', ['option_id' => $group->id, 'name' => 'Size', 'sort_order' => 0, 'values' => $values])
            ->assertRedirect($url)->assertSessionHasErrors('values');
        $response = $this->get($url)->assertOk()->assertSee('Giá trị phân loại tối đa 100 mục.')
            ->assertSee('data-unsaved-initial="true"', false);
        $this->assertStringContainsString($values.'</textarea>', $response->getContent());
        $this->assertSame($ids, $group->fresh('values')->values->pluck('id')->all());
    }

    public function test_nested_validation_messages_cover_numeric_string_and_array_limits(): void
    {
        try {
            StoreLabels::validate(
                ['quantity' => -1, 'name' => str_repeat('x', 101), 'values' => []],
                ['quantity' => 'integer|min:0', 'name' => 'string|max:100', 'values' => 'array|min:1']
            );
            $this->fail('Invalid input must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('phải từ 0 trở lên', $exception->errors()['quantity'][0]);
            $this->assertStringContainsString('không được dài hơn 100 ký tự', $exception->errors()['name'][0]);
            $this->assertStringContainsString('cần ít nhất 1 mục', $exception->errors()['values'][0]);
        }
    }

    private function product(): Product
    {
        return app(CreateProduct::class)->execute(['category_id' => Category::factory()->create()->id, 'name' => 'UX2 synthetic plant',
            'slug' => 'ux2-'.fake()->unique()->numberBetween(1, 999999), 'status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
    }

    public function test_banner_shared_category_is_optional_in_browser_but_product_category_stays_required(): void
    {
        $this->loginAsRole('content_manager');
        $html = $this->get($this->storeUrl('content', 'banners/create'))->assertOk()
            ->assertSee('Chỉ bắt buộc khi chọn vị trí Theo danh mục')->getContent();
        preg_match('/<select[^>]+id="field_category_id"[^>]*>/', $html, $match);
        $this->assertNotEmpty($match);
        $this->assertStringNotContainsString('required', $match[0]);
        $this->assertTrue(StoreResources::required('products', 'category_id', false));
    }

    private function gallery(Product $product): array
    {
        $first = $product->images()->create(['path' => 'products/ux2-a.webp', 'sort_order' => 0, 'alt_text' => 'First', 'is_primary' => true]);
        $second = $product->images()->create(['path' => 'products/ux2-b.webp', 'sort_order' => 1, 'alt_text' => 'Second', 'is_primary' => false]);

        return ['expected_version' => app(ProductImages::class)->version($product), 'primary_image_id' => $first->id,
            'images' => [['id' => $first->id, 'alt_text' => 'Changed first', 'sort_order' => 1], ['id' => $second->id, 'alt_text' => 'Changed second', 'sort_order' => 0]]];
    }

    private function order(): Order
    {
        $variant = app(CreateVariant::class)->execute($this->product(), ['sku' => 'UX2-'.fake()->unique()->numberBetween(1, 999999),
            'pricing_type' => 'FIXED', 'price' => 100000, 'quantity' => 10]);

        return app(CreateOrder::class)->execute(['customer_name' => 'UX2 Synthetic', 'customer_phone' => '0900000000',
            'shipping_address' => ['line1' => 'Synthetic address', 'country_code' => 'VN'],
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]]]);
    }

    public function test_image_batch_preserves_primary_when_unchanged_and_switches_exactly_once_with_metadata(): void
    {
        Storage::fake('public');
        $this->loginAsRole('product_manager');
        $product = $this->product();
        $data = $this->gallery($product);
        Storage::disk('public')->put('products/ux2-a.webp', 'kept-original-bytes');
        $url = $this->storeUrl('catalog', 'products/'.$product->id.'/actions/images-batch');
        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame((int) $data['primary_image_id'], $product->images()->where('is_primary', true)->sole()->id);
        $this->assertSame('Changed second', $product->images()->orderBy('sort_order')->first()->alt_text);
        $this->assertSame('kept-original-bytes', Storage::disk('public')->get('products/ux2-a.webp'));
        $data['expected_version'] = app(ProductImages::class)->version($product);
        $data['primary_image_id'] = $data['images'][1]['id'];
        $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame((int) $data['primary_image_id'], $product->images()->where('is_primary', true)->sole()->id);
        $this->assertDatabaseCount('product_images', 2);
    }

    public function test_image_batch_stale_invalid_duplicate_and_missing_rows_do_not_overwrite_metadata(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product();
        $data = $this->gallery($product);
        $url = $this->storeUrl('catalog', 'products/'.$product->id.'/actions/images-batch');
        $invalid = $data;
        $invalid['images'][1]['sort_order'] = -1;
        $this->postJson($url, $invalid)->assertUnprocessable();
        $missing = $data;
        array_pop($missing['images']);
        $this->postJson($url, $missing)->assertUnprocessable();
        $duplicate = $data;
        $duplicate['images'][1]['id'] = $data['images'][0]['id'];
        $this->postJson($url, $duplicate)->assertUnprocessable();
        $path = $data;
        $path['images'][0]['path'] = 'settings/replace.webp';
        $this->postJson($url, $path)->assertUnprocessable();
        $product->images()->whereKey($data['images'][1]['id'])->update(['alt_text' => 'Concurrent editor']);
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->assertSame('First', $product->images()->findOrFail($data['images'][0]['id'])->alt_text);
        $this->assertSame('Concurrent editor', $product->images()->findOrFail($data['images'][1]['id'])->alt_text);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_image_batch_rejects_foreign_ids_and_unauthorized_role_without_writes(): void
    {
        $this->loginAsRole('product_manager');
        $product = $this->product();
        $data = $this->gallery($product);
        $other = $this->product()->images()->create(['path' => 'products/other.webp']);
        $url = $this->storeUrl('catalog', 'products/'.$product->id.'/actions/images-batch');
        $forged = $data;
        $forged['images'][1]['id'] = $other->id;
        $this->postJson($url, $forged)->assertNotFound();
        $forged = $data;
        $forged['primary_image_id'] = $other->id;
        $this->postJson($url, $forged)->assertNotFound();
        $this->loginAsRole('order_manager');
        $this->postJson($url, $data)->assertForbidden();
        $this->assertSame('First', $product->images()->first()->alt_text);
        $this->assertNull($other->fresh()->alt_text);
    }

    public function test_order_queue_preserves_context_on_detail_and_status_submit_with_no_external_return_url(): void
    {
        $this->loginAsRole('order_manager');
        $order = $this->order();
        $context = ['q' => 'UX2 Synthetic', 'status' => 'NEW', 'origin' => 'live', 'sort' => 'oldest', 'page' => '2'];
        $url = $this->storeUrl('orders', 'orders/'.$order->id);
        $this->get($url.'?'.http_build_query(['queue' => $context]))->assertOk()->assertSee('Quay lại danh sách đang lọc')->assertSee('name="queue[page]"', false);
        $response = $this->post($url.'/actions/status', ['status' => 'CONFIRMED', 'expected_status' => 'NEW', 'queue' => $context])
            ->assertRedirect()->assertSessionHasNoErrors();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $returned);
        $this->assertEquals($context, $returned['queue']);
        $this->assertStringContainsString($url.'?', $response->headers->get('Location'));
        $bad = $context + ['return_url' => 'https://example.test/steal'];
        $this->getJson($url.'?'.http_build_query(['queue' => $bad]))->assertUnprocessable();
        $this->postJson($url.'/actions/status', ['status' => 'PACKING', 'expected_status' => 'CONFIRMED', 'queue' => $bad])->assertUnprocessable();
        $this->assertSame('CONFIRMED', $order->fresh()->status->value);
        $this->getJson($url.'?queue=bad')->assertUnprocessable();
    }

    public function test_next_order_is_bounded_by_filter_sort_and_live_open_status(): void
    {
        $this->loginAsRole('order_manager');
        $first = $this->order();
        $second = $this->order();
        $legacy = $this->order();
        $legacy->update(['is_legacy' => true]);
        $service = app(OrderQueue::class);
        $context = ['sort' => 'oldest', 'status' => 'NEW'];
        $this->assertSame($service->detailUrl($second, $context), $service->navigation($first, $context)['next']);
        $this->assertNull($service->navigation($second, $context)['next']);
        $this->assertNull($service->navigation($legacy, [])['next']);
        $this->assertNull($service->navigation($first, ['q' => 'Does not match'])['next']);
        $newest = ['sort' => 'newest'];
        $this->assertSame($service->detailUrl($first, $newest), $service->navigation($second, $newest)['next']);
        $this->get($this->storeUrl('orders', 'orders').'?status=NEW&sort=oldest')->assertOk()->assertSee('Lọc trạng thái đơn hàng')->assertSee('queue%5Bstatus%5D=NEW');
    }

    public function test_draft_article_preview_is_private_sanitized_and_handles_null_publish_date(): void
    {
        $article = Article::create(['article_category_id' => ArticleCategory::factory()->create()->id, 'title' => 'UX2 draft article',
            'slug' => 'ux2-draft-article', 'status' => 'DRAFT', 'content' => '<p>Safe text</p><script>unsafe()</script>', 'featured' => false, 'show_in_menu' => false]);
        $url = $this->storeUrl('content', 'articles/'.$article->id).'?preview=1';
        $this->get($url)->assertRedirect('/admin/auth/login');
        $this->loginAsRole('product_manager');
        $this->getJson($url)->assertForbidden();
        $this->loginAsRole('content_manager');
        $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('Xem trước nội bộ')->assertSee('Safe text')->assertDontSee('unsafe()');
        $this->get('/bai-viet/'.$article->slug.'?preview=1')->assertNotFound();
        $this->assertNull($article->fresh()->published_at);
        $this->assertSame('DRAFT', $article->fresh()->status->value);
    }

    public function test_seo_snippet_matches_saved_storefront_fallback_and_role_shortcuts_are_scoped(): void
    {
        $product = $this->product();
        app(Catalog::class)->reset();
        $product->load(['images', 'variants']);
        $actual = app(Seo::class)->product($product, []);
        $snippet = app(SeoSnippet::class)->forRecord($product);
        $this->assertSame($actual['title'], $snippet['title']);
        $this->assertSame($actual['description'], $snippet['description']);
        $this->assertSame($actual['canonical'], $snippet['canonical']);
        $this->loginAsRole('content_manager');
        $this->get($this->storeUrl('dashboard'))->assertOk()->assertSee('Soạn bài viết nháp')->assertDontSee('Tạo sản phẩm nháp')->assertDontSee('Xử lý đơn website mới');
        $this->loginAsRole('product_manager');
        $this->get($this->storeUrl('catalog', 'products/'.$product->id.'/edit'))->assertOk()->assertSee('SEO bản đã lưu');
        $this->getJson($this->storeUrl('content', 'banners/create'))->assertForbidden();
    }
}
