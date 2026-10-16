<?php

use App\Models\Category;
use App\Models\Product;

test('anyone can view public categories', function () {
    Category::factory()->count(3)->create();

    $response = $this->getJson('/api/categories');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name'],
            ],
        ]);
});

test('anyone can view products and featured products', function () {
    $category = Category::factory()->create();
    Product::factory()->count(5)->create(['category_id' => $category->id]);

    $response = $this->getJson('/api/products');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'categories',
            'featured_products',
            'products',
        ]);
});

test('anyone can filter products by category', function () {
    $cat1 = Category::factory()->create(['name' => 'Computers']);
    $cat2 = Category::factory()->create(['name' => 'Apparel']);

    Product::factory()->create(['category_id' => $cat1->id, 'name' => 'MacBook Pro']);
    Product::factory()->create(['category_id' => $cat2->id, 'name' => 'Cotton Hoodie']);

    $response = $this->getJson("/api/products?category_id={$cat1->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.data.0.name', 'MacBook Pro');
});

test('anyone can search products by keyword', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id, 'name' => 'Sony Wireless Headphones']);
    Product::factory()->create(['category_id' => $category->id, 'name' => 'Leather Wallet']);

    $response = $this->getJson('/api/products?search=Headphones');

    $response->assertStatus(200)
        ->assertJsonPath('data.data.0.name', 'Sony Wireless Headphones');
});

test('anyone can view a single product by id', function () {
    $product = Product::factory()->create(['name' => 'Specific Product']);

    $response = $this->getJson("/api/products/{$product->id}");

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Specific Product');
});
