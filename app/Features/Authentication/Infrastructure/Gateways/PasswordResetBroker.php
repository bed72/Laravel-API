<?php

namespace App\Features\Authentication\Infrastructure\Gateways;

use App\Features\Authentication\Domain\Gateways\PasswordResetBrokerInterface;
use App\Features\Authentication\Domain\Gateways\TokenIssuerInterface;
use App\Features\Authentication\Domain\Repositories\UserRepositoryInterface;
use App\Features\Users\Domain\Models\User;
use Illuminate\Support\Facades\Password;

class PasswordResetBroker implements PasswordResetBrokerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $repository,
        private readonly TokenIssuerInterface $tokenIssuer,
    ) {}

    public function createToken(User $user): string
    {
        return Password::broker()->createToken($user);
    }

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
                $this->tokenIssuer->revokeAll($user);
            },
        );

        return $status === Password::PASSWORD_RESET;
    }
}
