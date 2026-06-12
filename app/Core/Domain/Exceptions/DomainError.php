<?php

namespace App\Core\Domain\Exceptions;

use App\Core\Domain\Enums\HttpStatusCode;

enum DomainError: string
{
    case EmailAlreadyRegistered = 'email_already_registered';
    case InvalidCredentials = 'invalid_credentials';
    case ResetTokenInvalid = 'reset_token_invalid';

    public function status(): HttpStatusCode
    {
        return match ($this) {
            self::EmailAlreadyRegistered => HttpStatusCode::UnprocessableEntity,
            self::InvalidCredentials => HttpStatusCode::Unauthorized,
            self::ResetTokenInvalid => HttpStatusCode::BadRequest,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::EmailAlreadyRegistered => 'Este e-mail já está registrado.',
            self::InvalidCredentials => 'Credenciais inválidas.',
            self::ResetTokenInvalid => 'Token de redefinição inválido.',
        };
    }

    public function field(): ?string
    {
        return match ($this) {
            self::EmailAlreadyRegistered => 'email',
            default => null,
        };
    }

    public function throw(): never
    {
        throw new DomainException($this);
    }
}
