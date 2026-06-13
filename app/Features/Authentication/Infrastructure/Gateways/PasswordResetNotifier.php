<?php

namespace App\Features\Authentication\Infrastructure\Notifications;

use App\Features\Authentication\Domain\Contracts\PasswordResetNotifierInterface;
use App\Features\Authentication\Infrastructure\Jobs\SendResetPasswordJob;
use App\Features\Users\Domain\Models\User;

class PasswordResetNotifier implements PasswordResetNotifierInterface
{
    public function notify(User $user): void
    {
        SendResetPasswordJob::dispatch($user)->afterCommit();
    }
}
