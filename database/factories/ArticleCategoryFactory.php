<?php

namespace Database\Factories;

use App\Models\ArticleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ArticleCategory> */
class ArticleCategoryFactory extends Factory
{
    protected $model = ArticleCategory::class;

    public function definition(): array
    {
        return ['name' => 'Synthetic articles', 'slug' => fake()->unique()->slug(), 'status' => 'PUBLISHED', 'published_at' => now()->subDay()];
    }
}
