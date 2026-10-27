<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class CheckoutAttempt extends Model
{
    protected $casts = ['order_id' => 'integer'];

    protected $fillable = ['token_hash', 'owner_hash', 'quote_hash', 'order_id'];

    protected $hidden = ['token_hash', 'owner_hash', 'quote_hash'];
}
