<?php

namespace App\Features\Authentication\Domain\Contracts;

use App\Features\Users\Domain\Models\User;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

interface AuthenticationRepositoryInterface
{
    public function findUserById(int|string $id): ?User;

    public function findActiveUserByEmail(string $email): ?User;

    public function findUserIncludingTrashed(string $email): ?User;

    /** @param array<string, mixed> $data */
    public function createUser(array $data): User;

    public function createToken(User $user, string $name = 'api'): NewAccessToken;

    public function deleteAllTokens(User $user): void;

    public function deleteToken(PersonalAccessToken $token): void;

    public function rotateToken(PersonalAccessToken $oldToken, User $user): NewAccessToken;
}
