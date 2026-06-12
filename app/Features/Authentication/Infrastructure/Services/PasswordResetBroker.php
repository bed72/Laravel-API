<?php

namespace App\Features\Authentication\Infrastructure\Services;

use App\Features\Authentication\Domain\Contracts\AuthenticationRepositoryInterface;
use App\Features\Authentication\Domain\Contracts\PasswordResetBrokerInterface;
use App\Features\Users\Domain\Models\User;
use Illuminate\Support\Facades\Password;

class PasswordResetBroker implements PasswordResetBrokerInterface
{
    public function __construct(
        private readonly AuthenticationRepositoryInterface $repository,
    ) {}

    public function reset(User $user, string $token, string $newPassword): bool
    {
        $status = Password::broker()->reset(
            [
                'email' => $user->email,
                'token' => $token,
                'password' => $newPassword,
            ],
            function (User $user) use ($newPassword): void {
                $this->repository->updatePassword($user, $newPassword);
                $this->repository->deleteAllTokens($user);
            },
        );

        return $status === Password::PASSWORD_RESET;
    }
}
