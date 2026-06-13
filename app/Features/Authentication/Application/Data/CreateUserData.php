<?php

namespace App\Features\Authentication\Application\Data;

/**
 * Input for the sign-up use case: the data needed to create a user.
 */
final readonly class CreateUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
