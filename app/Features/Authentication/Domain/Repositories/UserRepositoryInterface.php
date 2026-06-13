<?php

namespace App\Features\Authentication\Domain\Repositories;

use App\Features\Users\Domain\Models\User;

interface UserRepositoryInterface
{
    
    /** @param array<string, mixed> $data */
    public function createUser(array $data): User;
    
    public function findUserById(int|string $id): ?User;

    public function findUserByEmail(string $email): ?User;

    public function updatePassword(User $user, string $newPassword): void;
}
