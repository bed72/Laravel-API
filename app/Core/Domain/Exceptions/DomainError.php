<?php

namespace App\Core\Domain\Exceptions;

use App\Core\Domain\Enums\HttpStatusCode;

enum DomainError: string
{
    case ResetTokenInvalid = 'reset_token_invalid';
    case InvalidCredentials = 'invalid_credentials';
    case EmailAlreadyRegistered = 'email_already_registered';

    public function status(): HttpStatusCode
    {
        return match ($this) {
            self::ResetTokenInvalid => HttpStatusCode::BadRequest,
            self::InvalidCredentials => HttpStatusCode::Unauthorized,
            self::EmailAlreadyRegistered => HttpStatusCode::UnprocessableEntity,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::InvalidCredentials => 'Credenciais inválidas.',
            self::ResetTokenInvalid => 'Token de redefinição inválido.',
            self::EmailAlreadyRegistered => 'Este e-mail já está registrado.',
        };
    }

    public function field(): ?string
    {
        return match ($this) {
            self::EmailAlreadyRegistered => 'email',
            self::InvalidCredentials, self::ResetTokenInvalid => null,
        };
    }

    public function throw(): never
    {
        throw new DomainException($this);
    }
}
