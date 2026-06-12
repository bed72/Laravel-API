<?php

use App\Features\Users\Domain\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

it('signs out the current session and deletes only that token', function () {
    $user = User::factory()->create();

    // Create two tokens
    $token1 = $user->createToken('api');
    $token2 = $user->createToken('api');

    $response = $this->withHeader('Authorization', 'Bearer '.$token1->plainTextToken)
        ->postJson('/api/auth/sign-out');

    $response->assertNoContent();

    // Token 1 should be deleted
    expect(PersonalAccessToken::find($token1->accessToken->id))->toBeNull();
    // Token 2 should still exist
    expect(PersonalAccessToken::find($token2->accessToken->id))->not->toBeNull();
});

it('rejects sign out without authentication', function () {
    $this->postJson('/api/auth/sign-out')
        ->assertUnauthorized();
});
