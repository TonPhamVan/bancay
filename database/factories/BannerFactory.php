<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Banner> */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    public function definition(): array
    {
        return ['name' => 'Synthetic banner', 'image' => 'banners/demo.jpg', 'position' => 'home_slider', 'status' => 'ACTIVE'];
    }
}
