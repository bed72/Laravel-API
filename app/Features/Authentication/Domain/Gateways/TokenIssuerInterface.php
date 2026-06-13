<?php

namespace App\Features\Authentication\Domain\Gateways;

use App\Features\Authentication\Application\Data\IssuedTokenData;
use App\Features\Users\Domain\Models\User;

/**
 * Owns the access-token lifecycle. The token store (Sanctum) is an
 * implementation detail behind this contract; the Domain depends only on it.
 * Token policy (TTL, rotation window) lives in the Service and is passed in —
 * this contract performs the mechanics only.
 */
interface TokenIssuerInterface
{
    public function revokeAll(User $user): void;

    public function revoke(IssuedTokenData $token): void;
    
    public function issue(User $user, int $ttlDays): IssuedTokenData;

    public function rotate(IssuedTokenData $token, User $user, int $ttlDays): IssuedTokenData;

}
