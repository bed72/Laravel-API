<?php

use App\Features\Users\Domain\Models\User;

it('signs in with valid credentials and returns token and user', function () {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/auth/sign-in', [
        'email' => 'user@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email'],
        ])
        ->assertJsonPath('user.email', 'user@example.com');

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

it('does not return the password in sign in response', function () {
    User::factory()->create([
        'email' => 'secure@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/auth/sign-in', [
        'email' => 'secure@example.com',
        'password' => 'password123',
    ]);

    expect($response->json('user'))->not->toHaveKey('password');
});

it('rejects sign in with wrong password', function () {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => 'correct-password',
    ]);

    $response = $this->postJson('/api/auth/sign-in', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('errors.0.code', 'invalid_credentials');
});

it('rejects sign in with nonexistent email', function () {
    $response = $this->postJson('/api/auth/sign-in', [
        'email' => 'ghost@example.com',
        'password' => 'password123',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('errors.0.code', 'invalid_credentials');
});

it('rejects sign in with missing fields', function (array $payload, string $field) {
    $this->postJson('/api/auth/sign-in', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', $field);
})->with([
    'missing email' => [['password' => 'password123'], 'email'],
    'missing password' => [['email' => 'user@example.com'], 'password'],
]);

it('returns same error for wrong password and nonexistent user', function () {
    User::factory()->create([
        'email' => 'real@example.com',
        'password' => 'password123',
    ]);

    $wrongPassword = $this->postJson('/api/auth/sign-in', [
        'email' => 'real@example.com',
        'password' => 'bad-password',
    ]);

    $noUser = $this->postJson('/api/auth/sign-in', [
        'email' => 'fake@example.com',
        'password' => 'password123',
    ]);

    // Both should be indistinguishable (non-enumeration)
    expect($wrongPassword->json('errors.0.code'))->toBe($noUser->json('errors.0.code'));
    expect($wrongPassword->status())->toBe($noUser->status());
});
