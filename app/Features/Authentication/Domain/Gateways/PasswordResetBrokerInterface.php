<?php

namespace App\Features\Authentication\Domain\Gateways;

use App\Features\Users\Domain\Models\User;

interface PasswordResetBrokerInterface
{
    /**
     * Create a password-reset token for the user.
     */
    public function createToken(User $user): string;

    /**
     * Reset the user's password using a valid token.
     * Returns true on success, false if the token is invalid/expired.
     */
    public function reset(User $user, string $token, string $newPassword): bool;
}
