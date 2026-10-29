<?php

namespace App\Services\Storefront;

use App\Models\Article;
use App\Models\Banner;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class Catalog
{
    private ?Collection $categories = null;

    private ?Collection $articleCategories = null;

    private ?array $settings = null;

    public function __construct(public readonly Presentation $display, private readonly Publication $publication) {}

    public function reset(): void
    {
        $this->categories = $this->articleCategories = null;
        $this->settings = null;
    }

    public function categories(): Collection
    {
        return $this->categories ??= $this->publication->categories();
    }

    public function articleCategories(): Collection
    {
        return $this->articleCategories ??= $this->publication->categories(true);
    }

    public function settings(): array
    {
        return $this->settings ??= Setting::whereIn('key', config('website.public_setting_keys'))->pluck('value', 'key')->all();
    }

    public function layout(): array
    {
        $settings = $this->settings();

        return ['settings' => $settings, 'name' => ($settings['site_name'] ?? null) ?: config('app.name'),
            'logo' => empty($settings['logo']) ? null : $this->display->image($settings['logo']),
            'navigation' => $this->categories(), 'articleNavigation' => $this->articleCategories()->where('show_in_menu', true),
            'cartCount' => collect(session('store.cart', []))->sum('quantity'),
            'links' => ['facebook' => $this->safeLink($settings['facebook'] ?? null), 'map' => $this->safeLink($settings['map'] ?? null),
                'email' => filter_var($settings['email'] ?? '', FILTER_VALIDATE_EMAIL) ? 'mailto:'.$settings['email'] : null,
                'phone' => empty($settings['contact_phone']) ? null : 'tel:'.preg_replace('/[^0-9+]/', '', $settings['contact_phone'])]];
    }

    public function products(): Builder
    {
        return Publication::scope(Product::query())->whereIn('category_id', $this->categories()->keys())
            ->with(['images', 'category', 'variants' => fn ($q) => $q->where('status', 'ACTIVE')->with('inventory')]);
    }

    public function listing(array $filters, ?int $category = null)
    {
        $query = $this->products();
        if ($category !== null) {
            $query->whereIn('category_id', $this->publication->descendants($this->categories(), $category));
        }
        if (! empty($filters['q'])) {
            $term = '%'.addcslashes($filters['q'], '\\%_').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhereHas('variants', fn ($v) => $v->where('status', 'ACTIVE')->where('sku', 'like', $term)));
        }
        // Fixed scalar subquery uses static SQL; no request values interpolated.
        if (in_array($filters['sort'] ?? '', ['price_asc', 'price_desc'], true)) {
            $query->select('products.*')->selectSub(ProductVariant::selectRaw('MIN(COALESCE(sale_price, price))')
                ->whereColumn('product_id', 'products.id')->where('status', 'ACTIVE')->where('pricing_type', 'FIXED'), 'listing_price')
                ->orderByRaw('listing_price IS NULL')->orderBy('listing_price', $filters['sort'] === 'price_desc' ? 'desc' : 'asc')->orderByDesc('id');
        } elseif (($filters['sort'] ?? '') === 'newest') {
            $query->orderByDesc('id');
        } else {
            $query->orderByDesc('featured')->orderByDesc('id');
        }

        return $query->paginate(24)->withQueryString()->through(fn ($product) => $this->display->card($product));
    }

    public function product(string $slug): Product
    {
        return $this->products()->where('slug', $slug)->firstOrFail()->loadMissing(['options.values', 'variants.optionValues', 'variants.image']);
    }

    public function picker(Product $product): array
    {
        $cover = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return ['product_id' => (string) $product->id, 'image' => $this->display->image($cover?->path),
            'options' => $product->options->map(fn ($o) => ['id' => (string) $o->id, 'name' => $o->name,
                'values' => $o->values->map(fn ($v) => ['id' => (string) $v->id, 'value' => $v->value])->values()])->values(),
            'variants' => $product->variants->map(fn ($v) => ['id' => (string) $v->id, 'sku' => $v->sku,
                'values' => $v->optionValues->mapWithKeys(fn ($value) => [(string) $value->product_option_id => (string) $value->id]),
                'price' => $this->display->price($v), 'contact' => $v->effectivePrice() === null,
                'available' => $v->inventory?->available() ?? 0, 'image' => $v->image ? $this->display->image($v->image->path) : null])->values()];
    }

    public function articles(): Builder
    {
        return Publication::scope(Article::query())->whereIn('article_category_id', $this->articleCategories()->keys())->with('category');
    }

    public function banners(string $position, ?int $category = null): Collection
    {
        return Banner::where('position', $position)->where('status', 'ACTIVE')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(function ($q) use ($category) {
                $q->whereNull('category_id');
                if ($category !== null) {
                    $q->orWhere('category_id', $category);
                }
            })
            ->orderByDesc('priority')->orderBy('sort_order')->orderByDesc('id')->limit(24)->get()
            ->map(fn ($b) => ['name' => $b->name, 'image' => $this->display->image($b->image),
                'link' => $this->safeLink($b->link) ?? route('store.products.index', [], false)]);
    }

    public function safeLink(?string $url): ?string
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    public function bannerSlots(?int $category = null): array
    {
        return ['sidebar' => $this->banners('sidebar', $category), 'content_bottom' => $this->banners('content_bottom', $category)];
    }

    public function breadcrumbs(?int $categoryId, bool $articles = false): array
    {
        $tree = $articles ? $this->articleCategories() : $this->categories();
        $crumbs = [];
        $seen = [];
        while ($categoryId && $tree->has($categoryId) && ! in_array($categoryId, $seen, true)) {
            $seen[] = $categoryId;
            $category = $tree->get($categoryId);
            array_unshift($crumbs, ['name' => $category->name, 'url' => route($articles ? 'store.article-categories.show' : 'store.categories.show', $category->slug, false)]);
            $categoryId = $category->parent_id === null ? null : (int) $category->parent_id;
        }
        array_unshift($crumbs, ['name' => 'Trang chủ', 'url' => '/']);

        return $crumbs;
    }
}
