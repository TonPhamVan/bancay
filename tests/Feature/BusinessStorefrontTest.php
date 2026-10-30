<?php

namespace Tests\Feature;

use App\Actions\Catalog\CreateProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BusinessStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultation_page_has_real_owned_contacts_and_discovery_links(): void
    {
        Setting::create(['key' => 'site_name', 'value' => 'QA Store']);
        Setting::create(['key' => 'contact_phone', 'value' => '0888.085.444']);
        Setting::create(['key' => 'facebook', 'value' => 'https://www.facebook.com/example.test']);
        $this->get('/tu-van-canh-quan')->assertOk()->assertSee('Tư vấn thiết kế')
            ->assertSee('tel:0888085444', false)->assertSee('https://www.facebook.com/example.test', false)
            ->assertSee('không phải hồ sơ công trình')->assertSee('Hà Nội')->assertSee('noindex');
        $this->get('/')->assertOk()->assertSee('/tu-van-canh-quan', false)->assertSee('Nhận tư vấn thiết kế');
        $this->get('/sitemap.xml')->assertOk()->assertSee('/tu-van-canh-quan', false);
        $this->post('/tu-van-canh-quan', [])->assertStatus(405);
    }

    public function test_consultation_falls_back_to_contact_without_inventing_phone_or_social(): void
    {
        $this->get('/tu-van-canh-quan')->assertOk()->assertSee('Xem thông tin liên hệ')
            ->assertDontSee('Gọi tư vấn:')->assertDontSee('Nhắn Facebook để tư vấn');
    }

    public function test_departments_use_actual_published_category_names_and_hide_empty_or_draft_content(): void
    {
        $indoor = Category::factory()->create(['name' => 'Cây cảnh Nội thất', 'slug' => 'indoor', 'status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
        $fertilizer = Category::factory()->create(['name' => 'Phân bón', 'slug' => 'fertilizer', 'status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
        Category::factory()->create(['name' => 'Thiết bị tưới', 'status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
        foreach ([$indoor, $fertilizer] as $category) {
            app(CreateProduct::class)->execute(['name' => 'QA '.$category->name, 'slug' => 'qa-'.$category->slug,
                'category_id' => $category->id, 'status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
        }
        $response = $this->get('/')->assertOk();
        $blocks = $response->viewData('departments');
        $this->assertCount(2, $blocks);
        $this->assertSame('/danh-muc/indoor', $blocks[0]['url']);
        $this->assertSame('/danh-muc/fertilizer', $blocks[1]['url']);
        $fertilizer->update(['status' => 'DRAFT']);
        $this->assertCount(1, $this->get('/')->viewData('departments'));
    }

    public function test_department_does_not_misclassify_a_plant_named_long_den_as_lighting(): void
    {
        $category = Category::factory()->create(['name' => 'Cây hoa leo']);
        app(CreateProduct::class)->execute(['name' => 'Cây hoa Lồng Đèn', 'slug' => 'long-den', 'category_id' => $category->id,
            'status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
        $this->assertSame([], $this->get('/')->assertOk()->viewData('departments'));
    }

    public function test_vietnamese_pagination_preserves_search_and_sort_query(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(26)->create(['name' => 'QA hàng', 'category_id' => $category->id, 'status' => 'PUBLISHED', 'published_at' => now()->subDay()]);
        $this->get('/tim-kiem?q=QA&sort=newest')->assertOk()->assertSee('Hiển thị 1–24 / 26 kết quả')
            ->assertSee('Điều hướng phân trang')->assertSee('page=2', false)->assertSee('q=QA', false)->assertSee('sort=newest', false)
            ->assertDontSee('Showing')->assertDontSee('Next');
        $this->get('/tim-kiem?q=QA&sort=newest&page=2')->assertOk()->assertSee('Hiển thị 25–26 / 26 kết quả');
    }
}
