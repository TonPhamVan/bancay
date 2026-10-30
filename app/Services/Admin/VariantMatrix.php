<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\ProductVariant;

final class VariantMatrix
{
    public function version(ProductVariant $variant): string
    {
        return hash_hmac('sha256', json_encode([
            (string) $variant->id, (string) $variant->product_id, $variant->sku,
            $variant->pricing_type->value, $variant->price, $variant->sale_price,
            $variant->status->value, $variant->image_id,
            $variant->inventory?->quantity, $variant->inventory?->reserved_quantity,
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public function rows(Product $product): array
    {
        $product->loadMissing(['variants.optionValues', 'variants.inventory']);

        return $product->variants->sortBy('id')->map(fn ($variant) => [
            'id' => (string) $variant->id,
            'label' => $variant->optionValues->pluck('value')->join(' / ') ?: 'Mặc định',
            'version' => $this->version($variant),
            'sku' => $variant->sku, 'pricing_type' => $variant->pricing_type->value,
            // Keep integer money/identifiers as strings across the JavaScript boundary.
            'price' => $variant->price === null ? '' : (string) $variant->price,
            'sale_price' => $variant->sale_price === null ? '' : (string) $variant->sale_price,
            'image_id' => $variant->image_id === null ? '' : (string) $variant->image_id,
            'status' => $variant->status->value,
            'quantity' => (string) ($variant->inventory?->quantity ?? 0),
            'reserved' => (string) ($variant->inventory?->reserved_quantity ?? 0),
            'available' => (string) ($variant->inventory?->available() ?? 0),
        ])->values()->all();
    }
}
