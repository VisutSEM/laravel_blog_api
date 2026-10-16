<?php

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;

test('authenticated user can view their cart and calculated total', function () {
    $user = User::factory()->create();
    $product1 = Product::factory()->create(['price' => 50, 'stock' => 10]);
    $product2 = Product::factory()->create(['price' => 25, 'stock' => 10]);

    Cart::factory()->create(['user_id' => $user->id, 'product_id' => $product1->id, 'quantity' => 2]); // 100
    Cart::factory()->create(['user_id' => $user->id, 'product_id' => $product2->id, 'quantity' => 1]); // 25

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/cart');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.total_amount', 125);
});

test('user can add a product to their cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/cart', [
        'product_id' => $product->id,
        'quantity'   => 2,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('carts', [
        'user_id'    => $user->id,
        'product_id' => $product->id,
        'quantity'   => 2,
    ]);
});

test('adding to cart validates available stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 3]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/cart', [
        'product_id' => $product->id,
        'quantity'   => 5,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error');
});

test('user can update item quantity in cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);
    $cart = Cart::factory()->create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);

    $response = $this->actingAs($user, 'sanctum')->putJson("/api/cart/{$cart->id}", [
        'quantity' => 4,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('carts', [
        'id'       => $cart->id,
        'quantity' => 4,
    ]);
});

test('user cannot update another users cart item', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);
    $cart = Cart::factory()->create(['user_id' => $user1->id, 'product_id' => $product->id, 'quantity' => 1]);

    $response = $this->actingAs($user2, 'sanctum')->putJson("/api/cart/{$cart->id}", [
        'quantity' => 3,
    ]);

    // Fails with 404 because user2 doesn't own that cart row
    $response->assertStatus(404);
});

test('user can remove an item from their cart', function () {
    $user = User::factory()->create();
    $cart = Cart::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')->deleteJson("/api/cart/{$cart->id}");

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseMissing('carts', [
        'id' => $cart->id,
    ]);
});
