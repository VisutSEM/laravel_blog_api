<?php

use App\Models\User;

test('a user can register with valid credentials', function () {
    $response = $this->postJson('/api/register', [
        'name'     => 'Jane Doe',
        'email'    => 'jane@example.com',
        'password' => 'secret123',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Registered successfully',
        ])
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
    ]);
});

test('a user cannot register with invalid or duplicate email', function () {
    User::factory()->create(['email' => 'duplicate@example.com']);

    $response = $this->postJson('/api/register', [
        'name'     => 'Jane Doe',
        'email'    => 'duplicate@example.com',
        'password' => 'secret123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('a user can login and receives a plain text sanctum token', function () {
    $user = User::factory()->create([
        'email'    => 'login@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email'    => 'login@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'name', 'email'],
            'token',
        ]);
});

test('a user can fetch their profile when authenticated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/profile');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data'    => [
                'id'    => $user->id,
                'email' => $user->email,
            ],
        ]);
});

test('a user can update their profile information', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile', [
        'name'  => 'New Updated Name',
        'phone' => '+987654321',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data'    => [
                'name'  => 'New Updated Name',
                'phone' => '+987654321',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'id'   => $user->id,
        'name' => 'New Updated Name',
    ]);
});

test('a user can logout and invalidates their token', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/logout');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'logout successful',
        ]);
});
