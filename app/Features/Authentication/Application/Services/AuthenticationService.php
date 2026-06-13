<?php

namespace App\Features\Authentication\Application\Services;

use App\Core\Domain\Exceptions\DomainError;
use App\Features\Authentication\Domain\Repositories\UserRepositoryInterface;
use App\Features\Authentication\Domain\Gateways\PasswordResetBrokerInterface;
use App\Features\Authentication\Domain\Gateways\PasswordResetNotifierInterface;
use App\Features\Authentication\Domain\Gateways\TokenIssuerInterface;
use App\Features\Authentication\Application\Data\AuthenticationSessionData;
use App\Features\Authentication\Application\Data\CreateUserData;
use App\Features\Authentication\Application\Data\IssuedTokenData;
use App\Features\Authentication\Application\Data\ResetPasswordData;
use App\Features\Users\Domain\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthenticationService
{
    /** Access tokens live for 30 days. */
    private const TOKEN_TTL_DAYS = 30;

    /** Tokens older than this are rotated on use. */
    private const TOKEN_ROTATION_DAYS = 7;

    public function __construct(
        private readonly TokenIssuerInterface $tokenIssuer,
        private readonly UserRepositoryInterface $repository,
        private readonly PasswordResetBrokerInterface $broker,
        private readonly PasswordResetNotifierInterface $notifier,
    ) {}

    /**
     * Sign up a new user, issue a token, and return user + access token.
     */
    public function signUp(CreateUserData $data): AuthenticationSessionData
    {
        $existing = $this->repository->findByEmail($data->email);

        if ($existing !== null) {
            DomainError::EmailAlreadyRegistered->throw();
        }

        $user = $this->repository->create($data);

        $token = $this->tokenIssuer->issue($user, self::TOKEN_TTL_DAYS);

        return new AuthenticationSessionData($user, $token);
    }

    /**
     * Sign in a user by verifying credentials and issuing a token.
     */
    public function signIn(string $email, string $password): AuthenticationSessionData
    {
        $user = $this->repository->findByEmail($email);

        if ($user === null) {
            DomainError::InvalidCredentials->throw();
        }

        if (! Hash::check($password, $user->password)) {
            DomainError::InvalidCredentials->throw();
        }

        return new AuthenticationSessionData(
            $user,
            $this->tokenIssuer->issue($user, self::TOKEN_TTL_DAYS),
        );
    }

    /**
     * Sign out the current session by revoking the specific token.
     */
    public function signOut(IssuedTokenData $token): void
    {
        $this->tokenIssuer->revoke($token);
    }

    /**
     * Log out from all devices by revoking all tokens for the user.
     */
    public function logOut(User $user): void
    {
        $this->tokenIssuer->revokeAll($user);
    }

    /**
     * Rotate the token if it crossed the freshness window.
     * Returns the new token, or null if rotation was not needed.
     */
    public function rotateTokenIfStale(IssuedTokenData $token, User $user): ?IssuedTokenData
    {
        if ($token->createdAt >= now()->subDays(self::TOKEN_ROTATION_DAYS)) {
            return null;
        }

        return $this->tokenIssuer->rotate($token, $user, self::TOKEN_TTL_DAYS);
    }

    /**
     * Request a password reset. Dispatches a job only if the user exists.
     * Always returns void (non-enumeration).
     */
    public function requestPasswordReset(string $email): void
    {
        $user = $this->repository->findByEmail($email);

        if ($user === null) {
            return;
        }

        $this->notifier->notify($user);
    }

    /**
     * Reset a user's password using a valid reset token.
     */
    public function resetPassword(ResetPasswordData $data): void
    {
        $user = $this->repository->findById($data->uid);

        if ($user === null) {
            DomainError::ResetTokenInvalid->throw();
        }

        $success = $this->broker->reset($user, $data->token, $data->newPassword);

        if (! $success) {
            DomainError::ResetTokenInvalid->throw();
        }
    }
}
