<?php

namespace App\Features\Authentication\Infrastructure\Jobs;

use App\Features\Authentication\Infrastructure\Notifications\ResetPasswordNotification;
use App\Features\Users\Domain\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Password;

class SendResetPasswordJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $user,
    ) {}

    public function handle(): void
    {
        $token = Password::broker()->createToken($this->user);

        $this->user->notify(new ResetPasswordNotification($token));
    }
}
