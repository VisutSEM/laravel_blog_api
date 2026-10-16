<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

test('user can checkout active cart into an order', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

    Cart::factory()->create([
        'user_id'    => $user->id,
        'product_id' => $product->id,
        'quantity'   => 2,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', [
        'shipping_address' => '456 Boulevard Avenue, NY',
        'payment_method'   => 'cash_on_delivery',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.total_amount', fn ($val) => (float) $val == 200.0);

    // Verify stock deduction
    expect($product->fresh()->stock)->toBe(8);

    // Verify cart is cleared
    expect(Cart::where('user_id', $user->id)->count())->toBe(0);

    // Verify order in database
    $this->assertDatabaseHas('orders', [
        'user_id'          => $user->id,
        'shipping_address' => '456 Boulevard Avenue, NY',
    ]);
});

test('checkout fails when cart has item exceeding stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock' => 2]);

    Cart::factory()->create([
        'user_id'    => $user->id,
        'product_id' => $product->id,
        'quantity'   => 5,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', [
        'shipping_address' => '456 Boulevard Avenue, NY',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('status', 'error');

    // Verify stock remains untouched
    expect($product->fresh()->stock)->toBe(2);
});

test('user can view list of their orders', function () {
    $user = User::factory()->create();
    Order::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/orders');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(3, 'data.data');
});

test('user can cancel a pending order and stock is restored', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 5]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status'  => 'pending',
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'quantity'   => 3,
        'unit_price' => $product->price,
    ]);

    $response = $this->actingAs($user, 'sanctum')->putJson("/api/orders/{$order->id}/cancel");

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success');

    expect($order->fresh()->status)->toBe('cancelled');
    // Product stock should have been incremented by 3
    expect($product->fresh()->stock)->toBe(8);
});
