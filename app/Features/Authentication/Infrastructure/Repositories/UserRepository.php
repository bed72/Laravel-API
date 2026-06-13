<?php

namespace App\Features\Authentication\Infrastructure\Repositories;

use App\Features\Authentication\Domain\Repositories\UserRepositoryInterface;
use App\Features\Users\Domain\Models\User;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly User $model,
    ) {}

    public function findUserByEmail(string $email): ?User
    {
        return $this->model->newQuery()->where('email', $email)->first();
    }

    public function findUserById(int|string $id): ?User
    {
        return $this->model->newQuery()->find($id);
    }

    public function createUser(string $name, string $email, string $password): User
    {
        /** @var User */
        return $this->model->newQuery()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    }

    public function updatePassword(User $user, string $newPassword): void
    {
        $user->password = $newPassword;
        $user->save();
    }
}
