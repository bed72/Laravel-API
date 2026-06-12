<?php

namespace App\Features\Authentication\Infrastructure\Jobs;

use App\Features\Authentication\Infrastructure\Notifications\ResetPasswordNotification;
use App\Features\Users\Domain\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendResetPasswordJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $user,
    ) {}

    public function uniqueId(): string
    {
        return 'password-reset-'.$this->user->id;
    }

    /**
     * Lock de unicidade expira em 5 minutos (tempo razoável para envio).
     */
    public int $uniqueFor = 300;

    public function handle(PasswordBroker $broker): void
    {
        $token = $broker->createToken($this->user);

        $this->user->notify(new ResetPasswordNotification($token));
    }
}
