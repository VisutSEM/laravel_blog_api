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

test('user can checkout with cash on delivery including shipping fee, notes, and order items snapshot', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'name'  => 'Espresso Roast Beans',
        'price' => 15.00,
        'stock' => 10,
        'image' => 'https://example.com/espresso.jpg',
    ]);

    Cart::factory()->create([
        'user_id'    => $user->id,
        'product_id' => $product->id,
        'quantity'   => 2,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', [
        'shipping_address' => 'St 214, Sangkat Boeung Raing, Phnom Penh',
        'payment_method'   => 'cash_on_delivery',
        'shipping_fee'     => 2.50,
        'notes'            => 'Please ring bell upon arrival',
        'address_id'       => 10,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Order placed successfully with Cash on Delivery!')
        ->assertJsonPath('order.payment_method', 'cash_on_delivery')
        ->assertJsonPath('order.payment_status', 'unpaid')
        ->assertJsonPath('order.status', 'pending')
        ->assertJsonPath('order.notes', 'Please ring bell upon arrival')
        ->assertJsonPath('order.address_id', 10)
        ->assertJsonPath('order.shipping_fee', '2.50')
        ->assertJsonPath('order.total_amount', '32.50');

    // Cart is cleared
    expect(Cart::where('user_id', $user->id)->count())->toBe(0);

    // Stock reduced by 2
    expect($product->fresh()->stock)->toBe(8);

    // Verify order in database
    $this->assertDatabaseHas('orders', [
        'user_id'          => $user->id,
        'payment_method'   => 'cash_on_delivery',
        'payment_status'   => 'unpaid',
        'status'           => 'pending',
        'total_amount'     => 32.50,
        'shipping_fee'     => 2.50,
        'notes'            => 'Please ring bell upon arrival',
    ]);

    // Verify order item snapshot
    $this->assertDatabaseHas('order_items', [
        'product_id'   => $product->id,
        'product_name' => 'Espresso Roast Beans',
        'price'        => 15.00,
        'quantity'     => 2,
        'image'        => 'https://example.com/espresso.jpg',
    ]);
});

test('user checkout with digital payment marks payment_status paid', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 50, 'stock' => 5]);

    Cart::factory()->create([
        'user_id'    => $user->id,
        'product_id' => $product->id,
        'quantity'   => 1,
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', [
        'shipping_address' => 'Russian Federation Blvd, Phnom Penh',
        'payment_method'   => 'aba_pay',
        'shipping_fee'     => 1.50,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('order.payment_method', 'aba_pay')
        ->assertJsonPath('order.payment_status', 'paid')
        ->assertJsonPath('order.status', 'pending')
        ->assertJsonPath('order.total_amount', '51.50');
});

test('cod order lifecycle automatically marks payment_status as paid on delivery', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 30, 'stock' => 10]);

    Cart::factory()->create([
        'user_id'    => $user->id,
        'product_id' => $product->id,
        'quantity'   => 1,
    ]);

    // 1. Checkout (pending, unpaid)
    $checkoutRes = $this->actingAs($user, 'sanctum')->postJson('/api/checkout', [
        'shipping_address' => 'Monivong Blvd, Phnom Penh',
        'payment_method'   => 'cash_on_delivery',
    ]);
    $orderId = $checkoutRes->json('order.id');

    // 2. Dispatch: processing -> shipped
    $dispatchRes = $this->actingAs($user, 'sanctum')->patchJson("/api/orders/{$orderId}/status", [
        'status' => 'processing',
    ]);
    $dispatchRes->assertStatus(200)
        ->assertJsonPath('order.status', 'processing')
        ->assertJsonPath('order.payment_status', 'unpaid');

    $shippedRes = $this->actingAs($user, 'sanctum')->patchJson("/api/orders/{$orderId}/status", [
        'status' => 'shipped',
    ]);
    $shippedRes->assertStatus(200)
        ->assertJsonPath('order.status', 'shipped')
        ->assertJsonPath('order.payment_status', 'unpaid');

    // 3. Delivery: rider delivers and collects cash -> status 'delivered', payment_status 'paid'
    $deliveredRes = $this->actingAs($user, 'sanctum')->patchJson("/api/orders/{$orderId}/status", [
        'status' => 'delivered',
    ]);
    $deliveredRes->assertStatus(200)
        ->assertJsonPath('order.status', 'delivered')
        ->assertJsonPath('order.payment_status', 'paid');

    expect(Order::find($orderId)->payment_status)->toBe('paid');
});

test('admin can view all orders across different users', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Order::factory()->count(2)->create(['user_id' => $user1->id]);
    Order::factory()->count(3)->create(['user_id' => $user2->id]);

    $response = $this->getJson('/api/admin/orders');

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonCount(5, 'data.data');
});

test('admin can approve delivery and COD order is automatically marked as paid', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 25.00, 'stock' => 5]);

    $order = Order::factory()->create([
        'user_id'        => $user->id,
        'payment_method' => 'cash_on_delivery',
        'payment_status' => 'unpaid',
        'status'         => 'shipped',
        'total_amount'   => 25.00,
    ]);

    $order->items()->create([
        'product_id'   => $product->id,
        'product_name' => $product->name,
        'quantity'     => 1,
        'unit_price'   => 25.00,
        'price'        => 25.00,
    ]);

    // Admin approves delivery
    $response = $this->postJson("/api/admin/orders/{$order->id}/approve-delivery", [
        'notes' => 'Cash collected $25.00 by rider Sok',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Order delivery approved successfully. Payment marked as paid.')
        ->assertJsonPath('order.status', 'delivered')
        ->assertJsonPath('order.payment_status', 'paid')
        ->assertJsonPath('order.notes', 'Cash collected $25.00 by rider Sok');

    expect($order->fresh()->status)->toBe('delivered');
    expect($order->fresh()->payment_status)->toBe('paid');
});

test('admin cannot approve delivery for a cancelled order', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'status'  => 'cancelled',
    ]);

    $response = $this->postJson("/api/admin/orders/{$order->id}/approve-delivery");

    $response->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Cannot approve delivery for a cancelled order.');
});
