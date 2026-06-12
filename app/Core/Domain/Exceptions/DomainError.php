<?php

namespace App\Core\Domain\Exceptions;

use App\Core\Domain\Enums\HttpStatusCode;

enum DomainError: string
{
    case EmailAlreadyRegistered = 'email_already_registered';
    case EmailBelongsToDeactivated = 'email_belongs_to_deactivated';
    case InvalidCredentials = 'invalid_credentials';
    case ResetTokenInvalid = 'reset_token_invalid';
    case PasswordWeak = 'password_weak';

    public function status(): HttpStatusCode
    {
        return match ($this) {
            self::EmailAlreadyRegistered,
            self::EmailBelongsToDeactivated,
            self::PasswordWeak => HttpStatusCode::UnprocessableEntity,
            self::InvalidCredentials => HttpStatusCode::Unauthorized,
            self::ResetTokenInvalid => HttpStatusCode::BadRequest,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::EmailAlreadyRegistered => 'Este e-mail já está registrado.',
            self::EmailBelongsToDeactivated => 'Este e-mail pertence a uma conta desativada.',
            self::InvalidCredentials => 'Credenciais inválidas.',
            self::ResetTokenInvalid => 'Token de redefinição inválido.',
            self::PasswordWeak => 'Você deve fornecer uma senha com pelo menos 8 caracteres.',
        };
    }

    public function field(): ?string
    {
        return match ($this) {
            self::EmailAlreadyRegistered,
            self::EmailBelongsToDeactivated => 'email',
            self::PasswordWeak => 'password',
            default => null,
        };
    }

    public function throw(): never
    {
        throw new DomainException($this);
    }
}
