<?php

namespace App\Models;

use App\Domain\Commerce\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['legacy_id', 'legacy_status', 'is_legacy', 'order_number', 'customer_id', 'customer_name', 'customer_email', 'customer_phone', 'shipping_address', 'shipping_note', 'currency', 'subtotal', 'discount_amount', 'shipping_fee', 'total', 'status'];

    protected $casts = ['status' => OrderStatus::class, 'shipping_address' => 'array', 'is_legacy' => 'boolean', 'subtotal' => 'integer', 'discount_amount' => 'integer', 'shipping_fee' => 'integer', 'total' => 'integer'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }
}
