<?php

use function Pest\Stressless\stress;

test('sign-in handles concurrent authentications under load', function () {
    // Seed user via API before stress test
    $this->postJson('/api/auth/sign-up', [
        'name' => 'Stress User',
        'email' => 'stress-signin@example.com',
        'password' => 'password123',
    ]);

    $result = stress('http://localhost/api/auth/sign-in')
        ->post([
            'email' => 'stress-signin@example.com',
            'password' => 'password123',
        ])
        ->concurrently(5)
        ->for(10)->seconds();

    expect($result->requests->failed->count)->toBe(0);
    expect($result->requests->duration->med)->toBeLessThan(250.0);
});

test('password reset request is stable under load', function () {
    $result = stress('http://localhost/api/auth/password/request')
        ->post([
            'email' => 'nonexistent@example.com',
        ])
        ->concurrently(5)
        ->for(10)->seconds();

    expect($result->requests->failed->count)->toBe(0);
    expect($result->requests->duration->med)->toBeLessThan(150.0);
});

test('validation errors respond quickly under load', function () {
    $result = stress('http://localhost/api/auth/sign-in')
        ->post([
            'email' => '',
            'password' => '',
        ])
        ->concurrently(10)
        ->for(10)->seconds();

    // 422 responses are expected (validation errors), not server errors
    expect($result->requests->duration->med)->toBeLessThan(100.0);
});
