<?php

namespace App\Features\Authentication\Domain\Contracts;

use App\Features\Users\Domain\Models\User;

interface PasswordResetBrokerInterface
{
    /**
     * Reseta a senha do usuário usando um token válido.
     * Retorna true se bem-sucedido, false se token inválido/expirado.
     */
    public function reset(User $user, string $token, string $newPassword): bool;
}
