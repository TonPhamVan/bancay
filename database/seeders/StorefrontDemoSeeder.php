<?php

namespace Database\Seeders;

use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\CreateVariant;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Explicit, repeatable local fixtures; never imports or updates legacy/customer records. */
final class StorefrontDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Storefront demo is local/testing only.');
        }
        foreach (['banners/phase3/hero.webp' => 'hero', 'products/phase3/lifestyle.webp' => 'hero', 'products/phase3/monstera.webp' => 'monstera',
            'products/phase3/zz.webp' => 'zz', 'categories/phase3/hero.webp' => 'hero',
            'articles/phase3/hero.webp' => 'hero'] as $path => $source) {
            if (! Storage::disk('public')->exists($path)) {
                Storage::disk('public')->put($path, file_get_contents(resource_path('demo-images/'.$source.'.webp')));
            }
        }
        DB::transaction(function () {
            $root = Category::firstOrCreate(['slug' => 'cay-trong-nha-demo'], ['name' => 'Cây trong nhà',
                'status' => 'PUBLISHED', 'published_at' => now()->subDay(), 'featured' => true, 'sort_order' => 10,
                'image' => 'categories/phase3/hero.webp', 'description' => '<p>Một chút xanh cho những không gian bạn yêu.</p>']);
            $desk = Category::firstOrCreate(['slug' => 'cay-de-ban-demo'], ['name' => 'Cây để bàn', 'parent_id' => $root->id,
                'status' => 'PUBLISHED', 'published_at' => now()->subDay(), 'featured' => true, 'sort_order' => 11,
                'image' => 'products/phase3/zz.webp']);
            $leaf = Category::firstOrCreate(['slug' => 'cay-la-kieng-demo'], ['name' => 'Cây lá kiểng', 'parent_id' => $root->id,
                'status' => 'PUBLISHED', 'published_at' => now()->subDay(), 'featured' => true, 'sort_order' => 12,
                'image' => 'products/phase3/monstera.webp']);
            $gift = Category::firstOrCreate(['slug' => 'goc-xanh-lam-qua-demo'], ['name' => 'Góc xanh làm quà',
                'status' => 'PUBLISHED', 'published_at' => now()->subDay(), 'featured' => true, 'sort_order' => 13,
                'image' => 'products/phase3/zz.webp']);
            $fixtures = [
                ['monstera-chau-dat-nung-demo', 'Trầu bà Monstera · Chậu đất nung', $leaf->id, 'monstera', 290000, 259000],
                ['kim-tien-chau-gom-demo', 'Kim tiền · Chậu gốm trắng', $desk->id, 'zz', 220000, null],
                ['monstera-goc-nang-demo', 'Monstera · Góc nắng nhỏ', $gift->id, 'monstera', 320000, 289000],
                ['kim-tien-ban-lam-viec-demo', 'Kim tiền · Bạn cùng bàn', $desk->id, 'zz', 250000, null],
            ];
            foreach ($fixtures as $index => [$slug, $name, $category, $image, $price, $sale]) {
                if ($existing = Product::where('slug', $slug)->first()) {
                    // Add the second illustration only to untouched fixtures from the initial Phase 3 seed.
                    if ($image === 'monstera' && str_contains($existing->description ?? '', 'minh họa demo')) {
                        $second = $existing->images()->firstOrCreate(['path' => 'products/phase3/lifestyle.webp'],
                            ['alt_text' => 'Góc vườn Monstera — ảnh minh họa demo', 'sort_order' => 1]);
                        $existing->variants()->where('sku', 'SF3-'.$index.'-1-0')->whereHas('image', fn ($q) => $q->where('path', 'products/phase3/monstera.webp'))->update(['image_id' => $second->id]);
                    }

                    continue;
                }
                $product = app(CreateProduct::class)->execute(['name' => $name, 'slug' => $slug, 'category_id' => $category,
                    'featured' => true, 'status' => 'PUBLISHED', 'published_at' => now()->subDay()->toDateTimeString(),
                    'short_description' => '<p>Một tán lá xanh làm dịu góc phòng và mang thiên nhiên đến gần bạn hơn.</p>',
                    'description' => '<h2>Một góc nhỏ, một niềm vui xanh</h2><p>Đặt cây ở nơi có ánh sáng gián tiếp, thoáng khí. Kiểm tra độ ẩm đất trước khi tưới và tránh để chậu ngâm nước lâu.</p><h2>Chọn cây cùng chúng mình</h2><p>Chọn kích thước phù hợp với không gian của bạn. Cửa hàng sẽ trao đổi thêm khi xác nhận đơn.</p><p><em>Sản phẩm và ảnh minh họa demo tổng hợp phục vụ kiểm thử local; không phải cam kết bán hàng thực tế.</em></p>',
                    'options' => [['name' => 'Kích thước', 'values' => ['Nhỏ', 'Tiêu chuẩn']], ['name' => 'Chuẩn bị', 'values' => ['Chậu kèm cây', 'Gói làm quà']]]]);
                $photo = $product->images()->create(['path' => 'products/phase3/'.$image.'.webp', 'alt_text' => $name.' — ảnh minh họa demo', 'is_primary' => true]);
                $lifestyle = $image === 'monstera' ? $product->images()->create(['path' => 'products/phase3/lifestyle.webp',
                    'alt_text' => 'Góc vườn Monstera — ảnh minh họa demo', 'sort_order' => 1]) : $photo;
                foreach ($product->options[0]->values as $i => $size) {
                    foreach ($product->options[1]->values as $j => $wrap) {
                        app(CreateVariant::class)->execute($product, ['sku' => 'SF3-'.$index.'-'.$i.'-'.$j,
                            'pricing_type' => $i === 1 && $j === 1 ? 'CONTACT' : 'FIXED',
                            'price' => $i === 1 && $j === 1 ? null : $price + $i * 50000 + $j * 20000,
                            'sale_price' => $i === 0 && $j === 0 ? $sale : null, 'quantity' => $i === 0 && $j === 1 ? 0 : 25,
                            'image_id' => $i === 0 ? $photo->id : $lifestyle->id, 'option_value_ids' => [$size->id, $wrap->id]]);
                    }
                }
            }
            $category = ArticleCategory::firstOrCreate(['slug' => 'cham-cay-demo'], ['name' => 'Chăm cây mỗi ngày',
                'status' => 'PUBLISHED', 'published_at' => now()->subDay(), 'show_in_menu' => true]);
            foreach ([['mot-goc-xanh-ben-cua-so-demo', 'Một góc xanh bên cửa sổ', 'hero'],
                ['lang-nghe-chiec-la-demo', 'Học cách lắng nghe chiếc lá', 'monstera'],
                ['cham-kim-tien-demo', 'Chăm một chậu kim tiền, bắt đầu từ đâu?', 'zz']] as [$slug, $title, $image]) {
                Article::firstOrCreate(['slug' => $slug], ['title' => $title, 'article_category_id' => $category->id,
                    'status' => 'PUBLISHED', 'published_at' => now()->subDay(),
                    'image' => $image === 'hero' ? 'articles/phase3/hero.webp' : 'products/phase3/'.$image.'.webp',
                    'excerpt' => 'Chậm lại một chút để nhìn màu lá, chạm vào đất và tìm nhịp chăm cây phù hợp với ngôi nhà.',
                    'content' => '<h2>Bắt đầu bằng việc quan sát</h2><p>Mỗi góc nhà có ánh sáng và độ ẩm khác nhau. Quan sát cây qua từng tuần để hiểu môi trường của cây trước khi thay đổi cách chăm sóc.</p><h2>Tưới khi cây cần</h2><p>Kiểm tra đất thay vì chỉ tưới theo lịch cố định. Giữ lỗ thoát nước thông thoáng, tránh để nước đọng dưới đáy chậu.</p><h2>Dành một chút thời gian</h2><p>Lau lá nhẹ nhàng, xoay chậu và quan sát những chiếc lá mới. Những thói quen nhỏ giúp bạn gần gũi với góc xanh của mình.</p><p><em>Nội dung và hình minh họa demo cho môi trường local.</em></p>']);
            }
            Banner::firstOrCreate(['name' => 'Phase 3 botanical hero demo'], ['image' => 'banners/phase3/hero.webp',
                'link' => rtrim(config('app.url'), '/').'/san-pham', 'position' => 'home_slider', 'status' => 'ACTIVE', 'priority' => 100]);
            Setting::firstOrCreate(['key' => 'site_name'], ['value' => 'bancay.vn']);
            $siteName = Setting::where('key', 'site_name')->first();
            if ($siteName?->value === 'Synthetic Bancay Store') {
                $siteName->update(['value' => 'bancay.vn']);
            }
            $metaTitle = Setting::where('key', 'meta_title')->first();
            if ($metaTitle?->value === 'Cửa hàng cây cảnh — kiểm tra Phase 2') {
                $metaTitle->update(['value' => 'bancay.vn']);
            }
            Setting::firstOrCreate(['key' => 'meta_description'], ['value' => 'Tìm một người bạn xanh cho không gian sống của bạn.']);
        });
    }
}
