<?php

namespace App\Features\Authentication\Infrastructure\Repositories;

use App\Features\Authentication\Application\Data\CreateUserData;
use App\Features\Authentication\Domain\Repositories\UserRepositoryInterface;
use App\Features\Users\Domain\Models\User;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly User $model,
    ) {}

    public function findByEmail(string $email): ?User
    {
        return $this->model->newQuery()->where('email', $email)->first();
    }

    public function findById(int|string $id): ?User
    {
        return $this->model->newQuery()->find($id);
    }

    public function create(CreateUserData $data): User
    {
        /** @var User */
        return $this->model->newQuery()->create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
        ]);
    }

    public function updatePassword(User $user, string $newPassword): void
    {
        $user->password = $newPassword;
        $user->save();
    }
}
