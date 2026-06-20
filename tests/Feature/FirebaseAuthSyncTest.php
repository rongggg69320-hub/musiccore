<?php

use App\Mail\OtpMail;
use App\Models\Otp;
use App\Models\User;
use App\Services\FirebaseAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('register creates a backend password user without firebase email auth', function () {
    $response = $this->postJson('/api/register', [
        'username' => 'newuser',
        'email' => 'newuser@example.com',
        'password' => 'secret123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('user.email', 'newuser@example.com')
        ->assertJsonPath('user.firebase_uid', null)
        ->assertJsonStructure(['token']);

    $user = User::where('email', 'newuser@example.com')->first();

    expect($user)->not->toBeNull()
        ->and(password_verify('secret123', $user->password))->toBeTrue();

    $this->assertDatabaseHas('roles', ['role_name' => 'user']);
    $this->assertDatabaseHas('users', [
        'email' => 'newuser@example.com',
        'username' => 'newuser',
        'firebase_uid' => null,
        'is_password_set' => true,
    ]);
});

test('login verifies backend email and password directly', function () {
    $role = \App\Models\Role::firstOrCreate(['role_name' => 'user']);

    User::create([
        'role_id' => $role->id,
        'username' => 'existing',
        'email' => 'existing@example.com',
        'password' => bcrypt('secret123'),
        'status' => 'active',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'existing@example.com',
        'password' => 'secret123',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.email', 'existing@example.com')
        ->assertJsonStructure(['token']);
});

test('login rejects invalid backend password', function () {
    $role = \App\Models\Role::firstOrCreate(['role_name' => 'user']);

    User::create([
        'role_id' => $role->id,
        'username' => 'wrongpass',
        'email' => 'wrongpass@example.com',
        'password' => bcrypt('secret123'),
        'status' => 'active',
    ]);

    $this->postJson('/api/login', [
        'email' => 'wrongpass@example.com',
        'password' => 'badpass123',
    ])->assertUnauthorized();
});

test('forgot password sends otp without creating firebase email account', function () {
    Mail::fake();
    $role = \App\Models\Role::firstOrCreate(['role_name' => 'user']);

    User::create([
        'role_id' => $role->id,
        'username' => 'otpuser',
        'email' => 'otpuser@example.com',
        'password' => bcrypt('oldpass123'),
        'status' => 'active',
    ]);

    $this->mock(FirebaseAuthService::class, function ($mock) {
        $mock->shouldNotReceive('findUidByEmail');
        $mock->shouldNotReceive('createEmailPasswordUser');
        $mock->shouldNotReceive('syncEmailPasswordUser');
    });

    $response = $this->postJson('/api/forgot-password', [
        'email' => 'otpuser@example.com',
    ]);

    $response->assertOk()->assertJsonPath('message', 'Verification code has been sent to your email.');

    $this->assertDatabaseHas('users', [
        'email' => 'otpuser@example.com',
        'firebase_uid' => null,
    ]);
    $this->assertDatabaseMissing('user_auth_providers', ['email' => 'otpuser@example.com']);
    $this->assertDatabaseHas('otps', ['email' => 'otpuser@example.com']);
    Mail::assertSent(OtpMail::class);
});

test('reset password updates only backend password', function () {
    $role = \App\Models\Role::firstOrCreate(['role_name' => 'user']);

    User::create([
        'role_id' => $role->id,
        'username' => 'resetuser',
        'email' => 'reset@example.com',
        'password' => bcrypt('oldpass123'),
        'firebase_uid' => 'social-or-old-firebase-uid',
        'status' => 'active',
    ]);

    Otp::create([
        'email' => 'reset@example.com',
        'otp' => '123456',
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->mock(FirebaseAuthService::class, function ($mock) {
        $mock->shouldNotReceive('syncEmailPasswordUser');
        $mock->shouldNotReceive('updatePassword');
    });

    $response = $this->postJson('/api/reset-password', [
        'email' => 'reset@example.com',
        'code' => '123456',
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ]);

    $response->assertOk()->assertJsonPath('message', 'Password reset.');

    $user = User::where('email', 'reset@example.com')->first();
    expect(password_verify('newpass123', $user->password))->toBeTrue();

    $this->assertDatabaseMissing('otps', ['email' => 'reset@example.com']);
});

test('change password updates only backend password', function () {
    $role = \App\Models\Role::firstOrCreate(['role_name' => 'user']);

    $user = User::create([
        'role_id' => $role->id,
        'username' => 'changepass',
        'email' => 'change@example.com',
        'password' => bcrypt('oldpass123'),
        'status' => 'active',
    ]);

    Sanctum::actingAs($user);

    $this->mock(FirebaseAuthService::class, function ($mock) {
        $mock->shouldNotReceive('syncEmailPasswordUser');
        $mock->shouldNotReceive('updatePasswordWithEmailPassword');
        $mock->shouldNotReceive('updatePassword');
    });

    $response = $this->postJson('/api/user/change-password', [
        'current_password' => 'oldpass123',
        'password' => 'newpass123',
        'password_confirmation' => 'newpass123',
    ]);

    $response->assertOk()->assertJsonPath('message', 'Password changed.');

    $user->refresh();
    expect(password_verify('newpass123', $user->password))->toBeTrue();
});

test('social login still links firebase provider to existing backend user', function () {
    $role = \App\Models\Role::firstOrCreate(['role_name' => 'user']);

    User::create([
        'role_id' => $role->id,
        'username' => 'socialuser',
        'email' => 'social@example.com',
        'password' => bcrypt('password'),
        'is_password_set' => true,
        'status' => 'active',
    ]);

    $this->mock(FirebaseAuthService::class, function ($mock) {
        $mock->shouldReceive('verifyIdToken')
            ->once()
            ->with('valid-google-token')
            ->andReturn([
                'sub' => 'firebase-google-uid',
                'email' => 'social@example.com',
                'email_verified' => true,
                'name' => 'Social User',
                'firebase' => [
                    'sign_in_provider' => 'google.com',
                ],
            ]);
    });

    $response = $this->postJson('/api/social-login', [
        'provider' => 'google',
        'provider_token' => 'valid-google-token',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.email', 'social@example.com')
        ->assertJsonPath('user.google_id', 'firebase-google-uid');

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('user_auth_providers', [
        'provider' => 'google',
        'firebase_uid' => 'firebase-google-uid',
        'email' => 'social@example.com',
    ]);
});
