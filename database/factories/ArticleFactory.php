<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Article> */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        return ['article_category_id' => ArticleCategory::factory(), 'title' => 'Synthetic article', 'slug' => fake()->unique()->slug(), 'content' => '<p>Synthetic content only.</p>', 'status' => 'PUBLISHED', 'published_at' => now()->subDay()];
    }
}
