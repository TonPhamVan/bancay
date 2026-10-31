<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\Product;
use App\Services\Storefront\Seo;

final class SeoSnippet
{
    /** Use the storefront's actual fallback formatter; do not guess search-engine presentation. */
    public function forRecord(Product|Article $record): array
    {
        $product = $record instanceof Product;
        $seo = app(Seo::class)->page($product ? $record->name : $record->title,
            route($product ? 'store.products.show' : 'store.articles.show', $record->slug, false),
            $record->meta_description ?: ($product ? strip_tags($record->short_description ?? '') : $record->excerpt));
        if ($record->meta_title) {
            $seo['title'] = $record->meta_title;
        }

        return $seo;
    }
}
