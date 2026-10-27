<?php

namespace App\Models;

use App\Domain\Catalog\PublicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleCategory extends Model
{
    use HasFactory;

    protected $fillable = ['legacy_id', 'parent_id', 'name', 'slug', 'description', 'image', 'sort_order', 'show_in_menu', 'status', 'meta_title', 'meta_description', 'published_at'];

    protected $casts = ['status' => PublicationStatus::class, 'show_in_menu' => 'boolean', 'sort_order' => 'integer', 'published_at' => 'immutable_datetime'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ArticleCategory::class, 'parent_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
