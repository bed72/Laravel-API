<?php

use App\Features\Users\Domain\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

// Feature: jwt-authentication, Property 17: Token expiration set to exactly 30 days
// Validates: Requirements 8.1

it('issues token with 30-day expiration on registration', function () {
    Carbon::setTestNow(now());

    $response = $this->postJson('/api/auth/sign-up', [
        'email' => 'test@example.com',
        'name' => 'Test User',
        'password' => 'password123',
    ]);

    $response->assertCreated();

    $token = PersonalAccessToken::first();
    $expectedExpiry = now()->addDays(30);

    expect($token->expires_at->diffInSeconds($expectedExpiry))->toBeLessThanOrEqual(2);

    Carbon::setTestNow();
});

it('issues token with 30-day expiration on sign-in', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    Carbon::setTestNow(now());

    $response = $this->postJson('/api/auth/sign-in', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk();

    $token = PersonalAccessToken::latest('id')->first();
    $expectedExpiry = now()->addDays(30);

    expect($token->expires_at->diffInSeconds($expectedExpiry))->toBeLessThanOrEqual(2);

    Carbon::setTestNow();
});

// Feature: jwt-authentication, Property 18: Token stored as SHA-256 hash only
// Validates: Requirements 8.3

it('stores token as SHA-256 hash that differs from plaintext', function () {
    $response = $this->postJson('/api/auth/sign-up', [
        'email' => 'hash@example.com',
        'name' => 'Hash User',
        'password' => 'password123',
    ]);

    $response->assertCreated();

    $plainTextToken = $response->json('token');
    // Sanctum prepends "{id}|" to the plaintext token
    $rawToken = explode('|', $plainTextToken, 2)[1] ?? $plainTextToken;

    $storedToken = PersonalAccessToken::first();

    // The stored token should be a 64-char hex string (SHA-256)
    expect($storedToken->token)->toHaveLength(64);
    expect($storedToken->token)->toMatch('/^[a-f0-9]{64}$/');
    // And it should NOT equal the plaintext
    expect($storedToken->token)->not->toBe($rawToken);
    expect($storedToken->token)->not->toBe($plainTextToken);
    // But it SHOULD equal the hash of the raw token
    expect($storedToken->token)->toBe(hash('sha256', $rawToken));
});
