<?php

namespace App\Features\Authentication\Domain\Gateways;

use App\Features\Users\Domain\Models\User;

interface PasswordResetNotifierInterface
{
    public function notify(User $user): void;
}
