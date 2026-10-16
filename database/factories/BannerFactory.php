<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

    public function definition(): array
    {
        return [
            'title'     => fake()->catchPhrase(),
            'image_url' => 'https://picsum.photos/seed/' . Str::random(6) . '/1200/500',
            'public_id' => 'banners/' . Str::random(12),
        ];
    }
}
