<?php

use App\Mail\ResetPasswordOtpMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('a user can request a password reset OTP via email', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'resetuser@example.com',
        'name'  => 'Test User',
    ]);

    $response = $this->postJson('/api/password/forgot', [
        'identifier' => 'resetuser@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data'   => [
                'type' => 'email',
            ],
        ]);

    Mail::assertSent(ResetPasswordOtpMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });

    $this->assertDatabaseHas('password_reset_codes', [
        'identifier' => 'resetuser@example.com',
        'type'       => 'email',
    ]);
});

test('a user can request a password reset OTP via phone number', function () {
    $user = User::factory()->create([
        'phone' => '+1234567890',
        'name'  => 'Phone User',
    ]);

    $response = $this->postJson('/api/password/forgot', [
        'identifier' => '+1234567890',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data'   => [
                'type' => 'phone',
            ],
        ]);

    $this->assertDatabaseHas('password_reset_codes', [
        'type' => 'phone',
    ]);
});

test('requesting password reset for non-existent account returns 404', function () {
    $response = $this->postJson('/api/password/forgot', [
        'identifier' => 'doesnotexist@example.com',
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'status' => 'error',
        ]);
});

test('submitting multiple OTP requests within 60 seconds triggers rate limiting', function () {
    User::factory()->create([
        'email' => 'ratelimit@example.com',
    ]);

    // First request
    $this->postJson('/api/password/forgot', [
        'identifier' => 'ratelimit@example.com',
    ])->assertStatus(200);

    // Immediate second request
    $secondResponse = $this->postJson('/api/password/forgot', [
        'identifier' => 'ratelimit@example.com',
    ]);

    $secondResponse->assertStatus(429)
        ->assertJson([
            'status' => 'error',
        ]);
});

test('a user can verify OTP and receives a single-use reset token', function () {
    $user = User::factory()->create([
        'email' => 'verifyotp@example.com',
    ]);

    $record = PasswordResetCode::create([
        'identifier' => 'verifyotp@example.com',
        'type'       => 'email',
        'otp'        => '123456',
        'attempts'   => 0,
        'expires_at' => now()->addMinutes(15),
    ]);

    // Test invalid OTP
    $wrongResponse = $this->postJson('/api/password/verify-otp', [
        'identifier' => 'verifyotp@example.com',
        'otp'        => '000000',
    ]);

    $wrongResponse->assertStatus(400)
        ->assertJson([
            'status' => 'error',
        ]);

    // Test correct OTP
    $correctResponse = $this->postJson('/api/password/verify-otp', [
        'identifier' => 'verifyotp@example.com',
        'otp'        => '123456',
    ]);

    $correctResponse->assertStatus(200)
        ->assertJson([
            'status' => 'success',
        ])
        ->assertJsonStructure([
            'data' => ['reset_token', 'identifier'],
        ]);

    $token = $correctResponse->json('data.reset_token');
    expect($token)->not->toBeEmpty();
});

test('a user can successfully reset their password with valid reset token', function () {
    $user = User::factory()->create([
        'email'    => 'newpassword@example.com',
        'password' => 'oldpassword123',
    ]);

    $resetToken = 'sample_valid_secure_reset_token_64chars_long_token_string_here_123';

    PasswordResetCode::create([
        'identifier'  => 'newpassword@example.com',
        'type'        => 'email',
        'otp'         => '654321',
        'reset_token' => $resetToken,
        'attempts'    => 0,
        'expires_at'  => now()->addMinutes(15),
        'verified_at' => now(),
    ]);

    $response = $this->postJson('/api/password/reset', [
        'identifier'            => 'newpassword@example.com',
        'reset_token'           => $resetToken,
        'password'              => 'brandnewpassword123',
        'password_confirmation' => 'brandnewpassword123',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
        ]);

    // Verify password is updated
    $user->refresh();
    expect(Hash::check('brandnewpassword123', $user->password))->toBeTrue();

    // Verify OTP record is deleted/cleaned up
    $this->assertDatabaseMissing('password_reset_codes', [
        'identifier' => 'newpassword@example.com',
    ]);
});
