<?php

namespace App\Features\Authentication\Infrastructure\Repositories;

use App\Features\Authentication\Domain\Contracts\AuthenticationRepositoryInterface;
use App\Features\Users\Domain\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticationRepository implements AuthenticationRepositoryInterface
{
    public function __construct(
        private readonly User $model,
    ) {}

    public function findActiveUserByEmail(string $email): ?User
    {
        return $this->model->newQuery()->where('email', $email)->first();
    }

    public function findUserIncludingTrashed(string $email): ?User
    {
        return $this->model->newQuery()->where('email', $email)->first();
    }

    public function findUserById(int|string $id): ?User
    {
        return $this->model->newQuery()->find($id);
    }

    /** @param array<string, mixed> $data */
    public function createUser(array $data): User
    {
        /** @var User */
        return $this->model->newQuery()->create($data);
    }

    public function createToken(User $user, string $name = 'api'): NewAccessToken
    {
        return $user->createToken($name, expiresAt: now()->addDays(30));
    }

    public function deleteToken(PersonalAccessToken $token): void
    {
        $token->delete();
    }

    public function deleteAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    public function rotateToken(PersonalAccessToken $oldToken, User $user): NewAccessToken
    {
        return DB::transaction(function () use ($oldToken, $user): NewAccessToken {
            $oldToken->delete();

            return $user->createToken('api', expiresAt: now()->addDays(30));
        });
    }
}
