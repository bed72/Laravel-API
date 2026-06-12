<?php

use App\Features\Authentication\Infrastructure\Jobs\SendResetPasswordJob;
use App\Features\Users\Domain\Models\User;
use Illuminate\Support\Facades\Queue;

it('dispatches reset job when email exists', function () {
    Queue::fake();

    $user = User::factory()->create(['email' => 'user@example.com']);

    $response = $this->postJson('/api/auth/password/request', [
        'email' => 'user@example.com',
    ]);

    $response->assertNoContent();

    Queue::assertPushed(SendResetPasswordJob::class, function ($job) {
        return true;
    });
});

it('returns success even when email does not exist (non-enumeration)', function () {
    Queue::fake();

    $response = $this->postJson('/api/auth/password/request', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertNoContent();

    Queue::assertNothingPushed();
});

it('rejects password reset request with invalid fields', function (array $payload, string $field) {
    $this->postJson('/api/auth/password/request', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', $field);
})->with([
    'missing email' => [[], 'email'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
]);

it('resets password with valid token', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    $token = app('auth.password.broker')->createToken($user);

    $response = $this->postJson('/api/auth/password/reset', [
        'uid' => $user->id,
        'token' => $token,
        'new_password' => 'new-secure-password',
    ]);

    $response->assertNoContent();

    // Verify the user can sign in with the new password
    $this->postJson('/api/auth/sign-in', [
        'email' => 'user@example.com',
        'password' => 'new-secure-password',
    ])->assertOk();
});

it('rejects password reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/auth/password/reset', [
        'uid' => $user->id,
        'token' => 'invalid-token',
        'new_password' => 'new-password123',
    ]);

    $response->assertBadRequest()
        ->assertJsonPath('errors.0.code', 'reset_token_invalid');
});

it('rejects password reset with nonexistent uid', function () {
    $response = $this->postJson('/api/auth/password/reset', [
        'uid' => '99999',
        'token' => 'some-token',
        'new_password' => 'new-password123',
    ]);

    $response->assertBadRequest()
        ->assertJsonPath('errors.0.code', 'reset_token_invalid');
});

it('rejects password reset with invalid fields', function (array $payload, string $field) {
    $this->postJson('/api/auth/password/reset', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', $field);
})->with([
    'missing uid' => [['token' => 'abc', 'new_password' => 'password123'], 'uid'],
    'missing token' => [['uid' => '1', 'new_password' => 'password123'], 'token'],
    'missing new_password' => [['uid' => '1', 'token' => 'abc'], 'new_password'],
    'new_password too short' => [['uid' => '1', 'token' => 'abc', 'new_password' => '1234567'], 'new_password'],
]);

it('invalidates all tokens after password reset', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    // Create some tokens
    $user->createToken('api');
    $user->createToken('api');

    $token = app('auth.password.broker')->createToken($user);

    $this->postJson('/api/auth/password/reset', [
        'uid' => $user->id,
        'token' => $token,
        'new_password' => 'new-password123',
    ])->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});
