<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(fake()->numberBetween(2, 4), true);

        return [
            'category_id' => Category::factory(),
            'name'        => ucfirst($name),
            'slug'        => Str::slug($name) . '-' . fake()->unique()->numberBetween(100, 99999),
            'description' => fake()->paragraph(),
            'price'       => fake()->randomFloat(2, 10, 499),
            'stock'       => fake()->numberBetween(5, 100),
            'is_featured' => fake()->boolean(30),
            'image'       => 'https://picsum.photos/seed/' . Str::random(8) . '/600/600',
        ];
    }
}
