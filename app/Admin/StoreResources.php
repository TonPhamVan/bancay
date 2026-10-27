<?php

namespace App\Admin;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;

/** Explicit native-table adapters; never accepts a table or class from a request. */
final class StoreResources
{
    public const MODULES = [
        'catalog' => ['categories', 'products', 'inventory'],
        'orders' => ['orders'], 'customers' => ['customers'],
        'content' => ['article-categories', 'articles', 'banners'], 'settings' => ['settings'],
    ];

    public const ABILITIES = ['catalog' => 'catalog.manage', 'orders' => 'commerce.manage',
        'customers' => 'customers.manage', 'content' => 'content.manage', 'settings' => 'settings.manage'];

    public static function all(): array
    {
        $seo = ['meta_title' => ['SEO title', 'text'], 'meta_description' => ['SEO description', 'textarea']];
        $publication = ['DRAFT' => 'Nháp', 'PUBLISHED' => 'Công khai', 'ARCHIVED' => 'Lưu trữ'];
        $active = ['ACTIVE' => 'Hoạt động', 'INACTIVE' => 'Tạm ngưng'];
        $category = ['name' => ['Tên', 'text'], 'slug' => ['Slug', 'text'], 'parent_id' => ['Danh mục cha', 'parents'],
            'description' => ['Mô tả', 'textarea'], 'image_upload' => ['Ảnh', 'file'], 'sort_order' => ['Thứ tự', 'number'],
            'status' => ['Trạng thái', $publication], 'published_at' => ['Ngày công khai', 'datetime-local']];

        return [
            'categories' => ['model' => Category::class, 'title' => 'Danh mục sản phẩm', 'display' => 'name',
                'fields' => $category + ['featured' => ['Nổi bật', 'checkbox']] + $seo],
            'products' => ['model' => Product::class, 'title' => 'Sản phẩm', 'display' => 'name', 'fields' => [
                'name' => ['Tên sản phẩm', 'text'], 'slug' => ['Slug', 'text'], 'category_id' => ['Danh mục', 'categories'],
                'short_description' => ['Mô tả ngắn', 'textarea'], 'description' => ['Nội dung HTML (được lọc)', 'textarea'],
                'featured' => ['Nổi bật', 'checkbox'], 'status' => ['Trạng thái', $publication],
                'published_at' => ['Ngày công khai', 'datetime-local']] + $seo],
            'article-categories' => ['model' => ArticleCategory::class, 'title' => 'Chuyên mục bài viết', 'display' => 'name',
                'fields' => $category + ['show_in_menu' => ['Hiện trong menu', 'checkbox']] + $seo],
            'articles' => ['model' => Article::class, 'title' => 'Bài viết', 'display' => 'title', 'fields' => [
                'title' => ['Tiêu đề', 'text'], 'slug' => ['Slug', 'text'], 'article_category_id' => ['Chuyên mục', 'article-categories'],
                'excerpt' => ['Tóm tắt', 'textarea'], 'content' => ['Nội dung HTML (được lọc)', 'textarea'],
                'image_upload' => ['Ảnh đại diện', 'file'], 'featured' => ['Nổi bật', 'checkbox'],
                'show_in_menu' => ['Hiện trong menu', 'checkbox'], 'status' => ['Trạng thái', $publication],
                'published_at' => ['Ngày công khai', 'datetime-local']] + $seo],
            'banners' => ['model' => Banner::class, 'title' => 'Banner / Slider', 'display' => 'name', 'fields' => [
                'name' => ['Tên', 'text'], 'image_upload' => ['Ảnh', 'file'], 'link' => ['Link HTTPS/HTTP', 'url'],
                'position' => ['Vị trí', config('website.banner_positions')], 'category_id' => ['Danh mục (tùy chọn)', 'categories'],
                'priority' => ['Ưu tiên', 'number'], 'sort_order' => ['Thứ tự', 'number'],
                'status' => ['Trạng thái', $active], 'starts_at' => ['Bắt đầu', 'datetime-local'], 'ends_at' => ['Kết thúc', 'datetime-local']]],
            'customers' => ['model' => Customer::class, 'title' => 'Khách hàng', 'display' => 'name', 'fields' => [
                'name' => ['Tên', 'text'], 'phone' => ['Điện thoại', 'text'], 'email' => ['Email', 'email'], 'status' => ['Trạng thái', $active]]],
            'inventory' => ['model' => Inventory::class, 'title' => 'Tồn kho', 'display' => 'id', 'fields' => []],
            'orders' => ['model' => Order::class, 'title' => 'Đơn hàng', 'display' => 'order_number', 'fields' => []],
            'settings' => ['model' => Setting::class, 'title' => 'Cấu hình cửa hàng', 'display' => 'key', 'fields' => []],
        ];
    }

    public static function get(string $module, string $entity): array
    {
        abort_unless(in_array($entity, self::MODULES[$module] ?? [], true), 404);

        return self::all()[$entity];
    }

    public static function url(string $module, string $path = ''): string
    {
        return admin_url('plugins/bancay_'.$module.($path !== '' ? '/'.$path : ''));
    }
}
