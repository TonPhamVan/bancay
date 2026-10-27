<?php

namespace App\Models;

use App\Domain\Catalog\RecordStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = ['legacy_source', 'legacy_id', 'name', 'image', 'link', 'position', 'category_id', 'priority', 'sort_order', 'status', 'starts_at', 'ends_at'];

    protected $casts = ['status' => RecordStatus::class, 'priority' => 'integer', 'sort_order' => 'integer', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
