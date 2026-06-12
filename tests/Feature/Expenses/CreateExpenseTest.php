<?php

use App\Features\Users\Domain\Models\User;

it('creates an expense with valid data', function () {
    // O controller atribui ao usuário 1 fixo; numa base limpa o 1º user vira id 1.
    User::factory()->create();

    $this->postJson('/api/expenses', [
        'amount' => 10.50,
        'description' => 'Almoco',
    ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'amount', 'description', 'created_at', 'updated_at']])
        ->assertJsonPath('data.amount', '10.50') // cast decimal:2 serializa como string
        ->assertJsonPath('data.description', 'Almoco');

    $this->assertDatabaseHas('expenses', [
        'amount' => 10.50,
        'description' => 'Almoco',
        'user_id' => 1,
    ]);
});

it('accepts an expense without a description', function () {
    User::factory()->create();

    $this->postJson('/api/expenses', ['amount' => 5])
        ->assertCreated()
        ->assertJsonPath('data.description', null);

    $this->assertDatabaseHas('expenses', ['amount' => 5, 'description' => null]);
});

it('rejects an invalid amount', function (mixed $amount) {
    $this->postJson('/api/expenses', ['amount' => $amount])
        ->assertStatus(422)
        ->assertJsonValidationErrors('amount');
})->with([
    'missing' => [null],
    'non-numeric' => ['abc'],
    'zero' => [0],
    'negative' => [-3.50],
]);

it('rejects a description longer than 32 chars', function () {
    $this->postJson('/api/expenses', [
        'amount' => 10,
        'description' => str_repeat('a', 33),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('description');
});
