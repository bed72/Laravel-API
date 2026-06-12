<?php

use App\Features\Users\Domain\Models\User;

it('signs up with valid data and returns token and user', function () {
    $response = $this->postJson('/api/auth/sign-up', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email'],
        ])
        ->assertJsonPath('user.name', 'John Doe')
        ->assertJsonPath('user.email', 'john@example.com');

    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com',
        'name' => 'John Doe',
    ]);
});

it('rejects sign up when email is already registered', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/auth/sign-up', [
        'name' => 'Another User',
        'email' => 'taken@example.com',
        'password' => 'password123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'email_already_registered')
        ->assertJsonPath('errors.0.field', 'email');
});

it('rejects sign up with invalid fields', function (array $payload, string $field) {
    $this->postJson('/api/auth/sign-up', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', $field);
})->with([
    'missing email' => [['name' => 'John', 'password' => 'password123'], 'email'],
    'invalid email' => [['name' => 'John', 'email' => 'not-an-email', 'password' => 'password123'], 'email'],
    'missing name' => [['email' => 'j@e.com', 'password' => 'password123'], 'name'],
    'name too long' => [['email' => 'j@e.com', 'name' => str_repeat('a', 129), 'password' => 'password123'], 'name'],
    'missing password' => [['email' => 'j@e.com', 'name' => 'John'], 'password'],
    'password too short' => [['email' => 'j@e.com', 'name' => 'John', 'password' => '1234567'], 'password'],
]);

it('does not return the password in sign up response', function () {
    $response = $this->postJson('/api/auth/sign-up', [
        'name' => 'Secure User',
        'email' => 'secure@example.com',
        'password' => 'password123',
    ]);

    $response->assertCreated();

    expect($response->json())->not->toHaveKey('password');
    expect($response->json('user'))->not->toHaveKey('password');
});
