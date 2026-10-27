<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'product_id', 'product_variant_id', 'product_name', 'sku', 'variant_description', 'unit_price', 'quantity', 'subtotal'];

    protected $casts = ['unit_price' => 'integer', 'quantity' => 'integer', 'subtotal' => 'integer'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $item): void {
            if ($item->isDirty(['order_id', 'product_name', 'sku', 'variant_description', 'unit_price', 'quantity', 'subtotal'])) {
                throw new \LogicException('Order item snapshots are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Historical order items cannot be deleted.'));
    }
}
