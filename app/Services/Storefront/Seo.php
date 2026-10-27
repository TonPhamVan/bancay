<?php

namespace App\Services\Storefront;

use App\Models\Article;
use App\Models\Product;

final class Seo
{
    public function __construct(private readonly Catalog $catalog) {}

    public function absolute(string $path): string
    {
        if (preg_match('#\Ahttps?://#i', $path)) {
            return $path;
        }

        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }

    public function page(string $title, string $path, ?string $description = null, bool $private = false, array $breadcrumbs = [], ?string $image = null): array
    {
        $settings = $this->catalog->settings();
        $site = ($settings['site_name'] ?? null) ?: config('app.name');
        $title = $title.' | '.(($settings['meta_title'] ?? null) ?: $site);
        $description = mb_substr(trim(strip_tags($description ?: ($settings['meta_description'] ?? '') ?: $site)), 0, 300);
        $structured = [];
        if ($breadcrumbs) {
            $structured[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(
                fn ($crumb, $index) => ['@type' => 'ListItem', 'position' => $index + 1, 'name' => $crumb['name'], 'item' => $this->absolute($crumb['url'])], $breadcrumbs, array_keys($breadcrumbs))];
        }

        $image ??= empty($settings['logo']) ? null : $this->catalog->display->image($settings['logo']);

        return ['title' => $title, 'description' => $description, 'canonical' => $this->absolute($path),
            'robots' => $private ? 'noindex, nofollow' : (config('storefront.indexable') ? 'index, follow' : 'noindex, follow'), 'image' => $image ? $this->absolute($image) : null, 'structured' => $structured];
    }

    public function product(Product $product, array $breadcrumbs): array
    {
        $card = $this->catalog->display->card($product);
        $seo = $this->page($product->name, $card['url'], $product->meta_description ?: strip_tags($product->short_description ?? ''), false, $breadcrumbs, $card['image']);
        if ($product->meta_title) {
            $seo['title'] = $product->meta_title;
        }
        $data = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product->name,
            'image' => [$this->absolute($card['image'])], 'description' => $seo['description'], 'url' => $seo['canonical']];
        $fixed = $product->variants->filter(fn ($v) => $v->status->value === 'ACTIVE' && $v->effectivePrice() !== null);
        if ($fixed->isNotEmpty()) {
            $data['offers'] = ['@type' => 'AggregateOffer', 'priceCurrency' => 'VND',
                'lowPrice' => (string) $fixed->min(fn ($v) => $v->effectivePrice()), 'highPrice' => (string) $fixed->max(fn ($v) => $v->effectivePrice()),
                'offerCount' => $fixed->count(), 'availability' => $fixed->contains(fn ($v) => ($v->inventory?->available() ?? 0) > 0) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'];
        }
        $seo['structured'][] = $data;

        return $seo;
    }

    public function article(Article $article, array $breadcrumbs): array
    {
        $image = $article->image ? $this->catalog->display->image($article->image) : null;
        $seo = $this->page($article->title, route('store.articles.show', $article->slug, false), $article->meta_description ?: $article->excerpt, false, $breadcrumbs, $image);
        if ($article->meta_title) {
            $seo['title'] = $article->meta_title;
        }
        $data = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $article->title,
            'datePublished' => $article->published_at->toIso8601String(), 'dateModified' => $article->updated_at->toIso8601String(),
            'mainEntityOfPage' => $seo['canonical']];
        if ($image) {
            $data['image'] = $this->absolute($image);
        }
        $seo['structured'][] = $data;

        return $seo;
    }
}
