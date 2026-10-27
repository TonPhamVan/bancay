<?php

namespace App\Models;

use App\Domain\Commerce\OrderStatus;
use Exceedone\Exment\Model\LoginUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'from_status', 'to_status', 'note', 'changed_by'];

    protected $casts = ['from_status' => OrderStatus::class, 'to_status' => OrderStatus::class, 'created_at' => 'immutable_datetime'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(LoginUser::class, 'changed_by');
    }

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Order status history is append-only.'));
        static::deleting(fn () => throw new \LogicException('Order status history is append-only.'));
    }
}
