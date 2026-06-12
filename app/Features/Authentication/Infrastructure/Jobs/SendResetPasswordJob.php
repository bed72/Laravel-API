<?php

namespace App\Features\Authentication\Infrastructure\Jobs;

use App\Features\Authentication\Infrastructure\Notifications\ResetPasswordNotification;
use App\Features\Users\Domain\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendResetPasswordJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $user,
    ) {}

    public function handle(PasswordBroker $broker): void
    {
        $token = $broker->createToken($this->user);

        $this->user->notify(new ResetPasswordNotification($token));
    }
}
