<?php

namespace App\Features\Authentication\Domain\Repositories;

use App\Features\Authentication\Application\Data\CreateUserData;
use App\Features\Users\Domain\Models\User;

interface UserRepositoryInterface
{
    public function create(CreateUserData $data): User;

    public function findById(int|string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function updatePassword(User $user, string $newPassword): void;
}
