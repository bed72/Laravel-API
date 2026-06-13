<?php

namespace App\Features\Authentication\Application\Data;

/**
 * Input for the password-reset confirmation use case.
 */
final readonly class ResetPasswordData
{
    public function __construct(
        public string $uid,
        public string $token,
        public string $newPassword,
    ) {}
}
