<?php

namespace App\Services\Storefront;

use App\Domain\Catalog\RecordStatus;
use App\Models\Article;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Rules\PublicImagePath;
use App\Support\IntegerDisplay;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

final class Presentation
{
    private array $imageUrls = [];

    public static function money(int|string $amount): string
    {
        return str_replace(',', '.', IntegerDisplay::format($amount)).' ₫';
    }

    public function image(?string $path): string
    {
        if (! $path) {
            return '/storefront/fallback.svg';
        }

        return $this->imageUrls[$path] ??= Validator::make(['path' => $path], ['path' => [new PublicImagePath]])->passes()
            && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : '/storefront/fallback.svg';
    }

    public function price(ProductVariant $variant): array
    {
        $price = $variant->effectivePrice();
        $discount = $variant->sale_price !== null && $variant->sale_price < $variant->price
            ? round(100 * (1 - $variant->sale_price / $variant->price), 1) : 0;

        return ['price' => $price, 'formatted' => $price === null ? 'Liên hệ' : self::money($price),
            'regular' => $discount > 0 ? self::money($variant->price) : null,
            'discount' => $discount > 0 ? '-'.str_replace('.', ',', (string) $discount).'%' : null];
    }

    public function card(Product $product): array
    {
        $variants = $product->variants->filter(fn ($v) => $v->status === RecordStatus::Active);
        $fixed = $variants->filter(fn ($v) => $v->effectivePrice() !== null)->sortBy(fn ($v) => $v->effectivePrice());
        $lowest = $fixed->first();
        $cover = $product->images->firstWhere('is_primary', true) ?? $product->images->first();

        return ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug,
            'url' => route('store.products.show', $product->slug, false), 'image' => $this->image($cover?->path),
            'alt' => $cover?->alt_text ?: $product->name, 'featured' => $product->featured,
            'price' => $lowest ? $this->price($lowest) : ['price' => null, 'formatted' => 'Liên hệ', 'regular' => null, 'discount' => null],
            'price_prefix' => $fixed->count() > 1 && $fixed->last()->effectivePrice() !== $lowest->effectivePrice() ? 'Từ ' : '',
            'available' => $variants->sum(fn ($v) => $v->inventory?->available() ?? 0),
        ];
    }

    public function articleCard(Article $article): array
    {
        return ['title' => $article->title, 'url' => route('store.articles.show', $article->slug, false),
            'category' => $article->category->name, 'image' => $this->image($article->image),
            'date' => $article->published_at->format('d.m.Y'), 'excerpt' => strip_tags($article->excerpt ?? '')];
    }
}
