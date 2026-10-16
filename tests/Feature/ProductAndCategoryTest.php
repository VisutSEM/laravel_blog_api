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

test('category creation validates required name and rejects duplicate name', function () {
    Category::create(['name' => 'Smartphones']);

    // Empty name
    $response = $this->postJson('/api/admin/categories', [
        'name' => '',
    ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    // Duplicate name
    $dupResponse = $this->postJson('/api/admin/categories', [
        'name' => 'Smartphones',
    ]);
    $dupResponse->assertStatus(422)
        ->assertJsonValidationErrors(['name'])
        ->assertJsonPath('errors.name.0', 'A category with this name already exists.');

    // Duplicate name with extra spaces
    $spaceResponse = $this->postJson('/api/categories', [
        'name' => '  Smartphones  ',
    ]);
    $spaceResponse->assertStatus(422)
        ->assertJsonValidationErrors(['name'])
        ->assertJsonPath('errors.name.0', 'A category with this name already exists.');

    // Unique valid name
    $validResponse = $this->postJson('/api/categories', [
        'name' => 'Accessories',
    ]);
    $validResponse->assertStatus(201)
        ->assertJsonPath('data.name', 'Accessories');
});

test('category update allows keeping same name but rejects duplicate from another category', function () {
    $cat1 = Category::create(['name' => 'Shoes']);
    $cat2 = Category::create(['name' => 'Bags']);

    // Updating cat1 with its own existing name is allowed
    $response = $this->putJson("/api/admin/categories/{$cat1->id}", [
        'name' => 'Shoes',
    ]);
    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'Shoes');

    // Updating cat1 with cat2\'s name is rejected as duplicate
    $dupResponse = $this->putJson("/api/admin/categories/{$cat1->id}", [
        'name' => 'Bags',
    ]);
    $dupResponse->assertStatus(422)
        ->assertJsonValidationErrors(['name'])
        ->assertJsonPath('errors.name.0', 'A category with this name already exists.');

    // Updating with brand new name is allowed
    $newResponse = $this->patchJson("/api/categories/{$cat1->id}", [
        'name' => 'Footwear',
    ]);
    $newResponse->assertStatus(200)
        ->assertJsonPath('data.name', 'Footwear');
});

