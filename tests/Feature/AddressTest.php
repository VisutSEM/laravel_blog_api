<?php

use App\Models\Adress;
use App\Models\User;

test('authenticated user can store a new address', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/addresses', [
        'city'      => 'Los Angeles',
        'street'    => '789 Sunset Blvd',
        'state'     => 'California',
        'latitude'  => 34.0522,
        'longitude' => -118.2437,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.city', 'Los Angeles');

    $this->assertDatabaseHas('adresses', [
        'user_id' => $user->id,
        'city'    => 'Los Angeles',
    ]);
});

test('authenticated user can retrieve their saved addresses', function () {
    $user = User::factory()->create();
    Adress::factory()->count(2)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/addresses');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data');
});

test('authenticated user can update their address', function () {
    $user = User::factory()->create();
    $address = Adress::factory()->create(['user_id' => $user->id, 'city' => 'Old City']);

    $response = $this->actingAs($user, 'sanctum')->putJson("/api/addresses/{$address->id}", [
        'city'   => 'New City',
        'street' => 'Updated Street 101',
        'state'  => 'Texas',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.city', 'New City');

    expect($address->fresh()->city)->toBe('New City');
});
