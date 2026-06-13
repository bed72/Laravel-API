<?php

namespace App\Features\Authentication\Infrastructure\Jobs;

use App\Features\Authentication\Domain\Gateways\PasswordResetBrokerInterface;
use App\Features\Authentication\Infrastructure\Notifications\ResetPasswordNotification;
use App\Features\Users\Domain\Models\User;
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
     * Uniqueness lock expires after 5 minutes (a reasonable send window).
     */
    public int $uniqueFor = 300;

    public function handle(PasswordResetBrokerInterface $broker): void
    {
        $token = $broker->createToken($this->user);

        $this->user->notify(new ResetPasswordNotification($token));
    }
}
