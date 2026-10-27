<?php

namespace App\Models;

use App\Domain\Catalog\PublicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use HasFactory;

    protected $fillable = ['legacy_id', 'article_category_id', 'title', 'slug', 'excerpt', 'content', 'image', 'status', 'featured', 'show_in_menu', 'view_count', 'published_at', 'meta_title', 'meta_description'];

    protected $casts = ['status' => PublicationStatus::class, 'featured' => 'boolean', 'show_in_menu' => 'boolean', 'view_count' => 'integer', 'published_at' => 'immutable_datetime'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'article_category_id');
    }
}
