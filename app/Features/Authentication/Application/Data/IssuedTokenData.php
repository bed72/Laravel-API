<?php

namespace App\Features\Authentication\Application\Data;

/**
 * Domain representation of an access token, decoupled from the issuing
 * infrastructure (Sanctum). `plainTextToken` is only populated for a freshly
 * issued token; a reference to an existing token carries id + createdAt only.
 */
final readonly class IssuedTokenData
{
    public function __construct(
        public int $id,
        public \DateTimeInterface $createdAt,
        public ?string $plainTextToken = null,
    ) {}
}
