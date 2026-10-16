<?php

use App\Models\Product;
use App\Models\User;

test('user can toggle product in their wishlist', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    // 1. Add to wishlist
    $response = $this->actingAs($user, 'sanctum')->postJson('/api/wishlist/toggle', [
        'product_id' => $product->id,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('added', true);

    expect($user->wishlist()->where('product_id', $product->id)->exists())->toBeTrue();

    // 2. Remove from wishlist
    $response2 = $this->actingAs($user, 'sanctum')->postJson('/api/wishlist/toggle', [
        'product_id' => $product->id,
    ]);

    $response2->assertStatus(200)
        ->assertJsonPath('added', false);

    expect($user->wishlist()->where('product_id', $product->id)->exists())->toBeFalse();
});

test('user can view their wishlist items', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $user->wishlist()->attach($product->id);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/wishlist');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(1, 'data');
});
