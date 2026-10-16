<?php

namespace Database\Seeders;

use App\Models\Adress;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create or update primary test user
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name'     => 'Demo User',
                'password' => Hash::make('password123'),
                'phone'    => '+1234567890',
            ]
        );

        // 2. Seed default categories
        $categoriesData = [
            'Electronics',
            'Fashion & Apparel',
            'Home & Kitchen',
            'Beauty & Wellness',
            'Sports & Fitness',
        ];

        $categories = collect($categoriesData)->map(function ($name) {
            return Category::firstOrCreate(['name' => $name]);
        });

        // 3. Seed products for each category
        foreach ($categories as $category) {
            for ($i = 1; $i <= 4; $i++) {
                $name = "{$category->name} Item {$i}";
                Product::firstOrCreate(
                    [
                        'category_id' => $category->id,
                        'name'        => $name,
                    ],
                    [
                        'slug'        => Str::slug($name) . '-' . $category->id . '-' . $i,
                        'description' => "High quality {$category->name} product designed for daily use and maximum performance.",
                        'price'       => 19.99 * $i,
                        'stock'       => 25 + ($i * 5),
                        'is_featured' => ($i % 2 === 0),
                        'image'       => "https://picsum.photos/seed/{$category->id}_{$i}/600/600",
                    ]
                );
            }
        }

        // 4. Seed banners
        if (Banner::count() === 0) {
            Banner::create([
                'title'     => 'Mega Summer Sale - Up to 50% Off!',
                'image_url' => 'https://picsum.photos/seed/banner1/1200/500',
                'public_id' => 'banners/summer_sale_1',
            ]);

            Banner::create([
                'title'     => 'New Arrivals - Latest Trends & Gadgets',
                'image_url' => 'https://picsum.photos/seed/banner2/1200/500',
                'public_id' => 'banners/new_arrivals_2',
            ]);
        }

        // 5. Seed demo user address
        if ($user->addresses()->count() === 0) {
            Adress::create([
                'user_id'   => $user->id,
                'street'    => '123 Market Street, Apt 4B',
                'city'      => 'San Francisco',
                'state'     => 'California',
                'latitude'  => 37.7749,
                'longitude' => -122.4194,
            ]);
        }
    }
}
