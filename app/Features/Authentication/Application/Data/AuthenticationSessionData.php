<?php

namespace App\Features\Authentication\Application\Data;

use App\Features\Users\Domain\Models\User;

/**
 * Result of a successful authentication (sign up / sign in): the authenticated
 * user paired with the freshly issued access token.
 */
final readonly class AuthenticationSessionData
{
    public function __construct(
        public User $user,
        public IssuedTokenData $token,
    ) {}
}
