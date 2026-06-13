<?php

namespace App\Features\Authentication\Infrastructure\Gateways;

use App\Features\Authentication\Domain\Gateways\PasswordResetNotifierInterface;
use App\Features\Authentication\Infrastructure\Jobs\SendResetPasswordJob;
use App\Features\Users\Domain\Models\User;

class PasswordResetNotifier implements PasswordResetNotifierInterface
{
    public function notify(User $user): void
    {
        SendResetPasswordJob::dispatch($user)->afterCommit();
    }
}
