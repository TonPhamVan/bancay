<?php

namespace App\Models;

use App\Domain\Catalog\PricingType;
use App\Domain\Catalog\RecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'legacy_variant_index', 'legacy_sku', 'sku', 'combination_key', 'pricing_type', 'price', 'sale_price', 'status', 'image_id'];

    protected $casts = ['status' => RecordStatus::class, 'pricing_type' => PricingType::class, 'price' => 'integer', 'sale_price' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_variant_values')->withPivot('product_option_id', 'product_id')->with('option');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductVariantValue::class);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function effectivePrice(): ?int
    {
        return $this->pricing_type === PricingType::Contact ? null : ($this->sale_price ?? $this->price);
    }
}
