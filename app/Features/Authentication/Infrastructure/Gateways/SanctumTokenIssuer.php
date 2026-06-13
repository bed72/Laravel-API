<?php

namespace App\Features\Authentication\Infrastructure\Gateways;

use App\Features\Authentication\Domain\Gateways\TokenIssuerInterface;
use App\Features\Authentication\Application\Data\IssuedTokenData;
use App\Features\Users\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum-backed implementation of the token lifecycle. This is the single
 * place in the feature allowed to reference Laravel\Sanctum\* — the Domain
 * never sees it.
 */
class SanctumTokenIssuer implements TokenIssuerInterface
{
    public function issue(User $user, int $ttlDays): IssuedTokenData
    {
        $new = $user->createToken('api', expiresAt: now()->addDays($ttlDays));

        return new IssuedTokenData(
            id: $new->accessToken->getKey(),
            createdAt: $new->accessToken->created_at,
            plainTextToken: $new->plainTextToken,
        );
    }

    public function rotate(IssuedTokenData $token, User $user, int $ttlDays): IssuedTokenData
    {
        return DB::transaction(function () use ($token, $user, $ttlDays): IssuedTokenData {
            $this->revoke($token);

            return $this->issue($user, $ttlDays);
        });
    }

    public function revoke(IssuedTokenData $token): void
    {
        PersonalAccessToken::query()->whereKey($token->id)->delete();
    }

    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();
    }
}
