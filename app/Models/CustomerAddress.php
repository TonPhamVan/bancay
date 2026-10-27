<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = ['customer_id', 'label', 'recipient_name', 'phone', 'line1', 'line2', 'ward', 'district', 'province', 'country_code', 'postal_code', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
