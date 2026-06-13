<?php

use App\Features\Users\Domain\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('logs out from all devices by deleting all tokens', function () {
    $user = User::factory()->create();

    $token1 = $user->createToken('api');
    $user->createToken('api');
    $user->createToken('api');

    $response = $this->withHeader('Authorization', 'Bearer '.$token1->plainTextToken)
        ->postJson('/api/auth/sign-out-all');

    $response->assertNoContent();

    expect(PersonalAccessToken::where('tokenable_id', $user->id)->count())->toBe(0);
});

it('rejects logout without authentication', function () {
    $this->postJson('/api/auth/sign-out-all')
        ->assertUnauthorized();
});
