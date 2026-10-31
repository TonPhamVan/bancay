<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Services\Storefront\Publication;

/** Advisory checklist: never changes publication, prices, stock or CONTACT policy. */
final class ProductReadiness
{
    public function inspect(Product $product): array
    {
        $product->loadMissing(['images', 'options.values', 'variants.inventory']);
        $active = $product->variants->where('status.value', 'ACTIVE');
        $visibleCategory = app(Publication::class)->categories()->has($product->category_id);
        $published = $product->status->value === 'PUBLISHED' && $product->published_at && ! $product->published_at->isFuture();
        $checks = [
            ['ok' => $visibleCategory, 'label' => 'Danh mục và toàn bộ danh mục cha đang công khai'],
            ['ok' => $published, 'label' => 'Sản phẩm công khai và đã đến ngày hiển thị'],
            ['ok' => $product->images->isNotEmpty(), 'label' => 'Đã có ảnh sản phẩm'],
            ['ok' => $active->isNotEmpty(), 'label' => 'Có ít nhất một SKU hoạt động'],
            ['ok' => $active->isNotEmpty() && $active->every(fn ($v) => $v->pricing_type->value === 'CONTACT'
                || ($v->price > 0 && ($v->sale_price === null || $v->sale_price <= $v->price))),
                'label' => 'Giá SKU hợp lệ (Liên hệ là lựa chọn hợp lệ)'],
            ['ok' => trim(strip_tags($product->description ?? '')) !== '', 'label' => 'Đã có mô tả sản phẩm'],
            ['ok' => $active->contains(fn ($v) => ($v->inventory?->available() ?? 0) > 0),
                'label' => 'Có SKU còn tồn khả dụng (thực tế trừ giữ chỗ); hết hàng vẫn có thể hiển thị'],
        ];
        $combinations = 1;
        foreach ($product->options as $option) {
            // Only a bounded preview is needed; never enumerate a Cartesian product here.
            $combinations = min(257, $combinations * $option->values->count());
        }

        return ['checks' => $checks, 'visible' => $visibleCategory && $published,
            'buyable' => $active->contains(fn ($v) => $v->effectivePrice() !== null && ($v->inventory?->available() ?? 0) > 0),
            'combinations' => $combinations, 'existing' => $product->variants->count()];
    }
}
