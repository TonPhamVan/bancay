<?php

namespace App\Services\Admin;

use App\Admin\StoreResources;
use App\Models\Product;
use App\Services\Storefront\Catalog;
use App\Services\Storefront\ContentHtml;
use App\Services\Storefront\Seo;

final class ProductPreview
{
    /** Called only after the native catalog permission check, never from a public resolver. */
    public function response(Product $product)
    {
        $catalog = app(Catalog::class);
        $catalog->reset();
        $product->load(['images', 'options.values', 'variants' => fn ($q) => $q->where('status', 'ACTIVE')->with(['inventory', 'optionValues', 'image'])]);
        $seo = app(Seo::class)->product($product, []);
        $seo['robots'] = 'noindex, nofollow';
        $seo['structured'] = [];

        return response()->view('products.show', [
            'product' => $product, 'picker' => $catalog->picker($product), 'store' => $catalog->layout(),
            'crumbs' => [['name' => 'Quay lại quản lý sản phẩm', 'url' => StoreResources::url('catalog', 'products/'.$product->id)]],
            'seo' => $seo, 'gallery' => $product->images->map(fn ($i) => ['url' => $catalog->display->image($i->path), 'alt' => $i->alt_text ?: $product->name]),
            'related' => collect(), 'preview' => true,
            'shortHtml' => app(ContentHtml::class)->render($product->short_description),
            'contentHtml' => app(ContentHtml::class)->render($product->description),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
