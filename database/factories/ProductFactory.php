<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return ['category_id' => Category::factory(), 'name' => fake()->words(3, true), 'slug' => fake()->unique()->slug(), 'status' => 'PUBLISHED', 'published_at' => now()->subDay()];
    }
}
