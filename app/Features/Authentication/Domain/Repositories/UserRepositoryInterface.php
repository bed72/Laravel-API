<?php

namespace App\Features\Authentication\Domain\Repositories;

use App\Features\Users\Domain\Models\User;

interface UserRepositoryInterface
{
    public function createUser(string $name, string $email, string $password): User;

    public function findUserById(int|string $id): ?User;

    public function findUserByEmail(string $email): ?User;

    public function updatePassword(User $user, string $newPassword): void;
}
