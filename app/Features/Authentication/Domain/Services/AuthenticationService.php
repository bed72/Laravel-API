<?php

namespace App\Features\Authentication\Domain\Services;

use App\Core\Domain\Exceptions\DomainError;
use App\Features\Authentication\Domain\Contracts\AuthenticationRepositoryInterface;
use App\Features\Authentication\Domain\Contracts\PasswordResetBrokerInterface;
use App\Features\Authentication\Domain\Contracts\PasswordResetNotifierInterface;
use App\Features\Users\Domain\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticationService
{
    private const TOKEN_ROTATION_DAYS = 7;

    public function __construct(
        private readonly PasswordResetNotifierInterface $notifier,
        private readonly AuthenticationRepositoryInterface $repository,
        private readonly PasswordResetBrokerInterface $broker,
    ) {}

    /**
     * Sign up a new user, issue a token, and return user + access token.
     *
     * @return array{user: User, token: NewAccessToken}
     */
    public function signUp(string $name, string $email, string $password): array
    {
        $existing = $this->repository->findActiveUserByEmail($email);

        if ($existing !== null) {
            DomainError::EmailAlreadyRegistered->throw();
        }

        $user = $this->repository->createUser([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $accessToken = $this->repository->createToken($user);

        return [
            'user' => $user,
            'token' => $accessToken,
        ];
    }

    /**
     * Sign in a user by verifying credentials and issuing a token.
     */
    public function signIn(string $email, string $password): NewAccessToken
    {
        $user = $this->repository->findActiveUserByEmail($email);

        if ($user === null) {
            DomainError::InvalidCredentials->throw();
        }

        if (! Hash::check($password, $user->password)) {
            DomainError::InvalidCredentials->throw();
        }

        return $this->repository->createToken($user);
    }

    /**
     * Sign out the current session by deleting the specific token.
     */
    public function signOut(PersonalAccessToken $token): void
    {
        $this->repository->deleteToken($token);
    }

    /**
     * Log out from all devices by deleting all tokens for the user.
     */
    public function logOut(User $user): void
    {
        $this->repository->deleteAllTokens($user);
    }

    /**
     * Rotaciona o token se ultrapassou a janela de frescura.
     * Retorna o novo token ou null se rotação não foi necessária.
     */
    public function rotateTokenIfStale(PersonalAccessToken $token, User $user): ?NewAccessToken
    {
        $staleThreshold = now()->subDays(self::TOKEN_ROTATION_DAYS);

        if ($token->created_at->isAfter($staleThreshold)) {
            return null;
        }

        return $this->repository->rotateToken($token, $user);
    }

    /**
     * Request a password reset. Dispatches a job only if the user exists.
     * Always returns void (non-enumeration).
     */
    public function requestPasswordReset(string $email): void
    {
        $user = $this->repository->findActiveUserByEmail($email);

        if ($user === null) {
            return;
        }

        $this->notifier->notify($user);
    }

    /**
     * Reset a user's password using a valid reset token.
     */
    public function resetPassword(string $uid, string $token, string $newPassword): void
    {
        $user = $this->repository->findUserById($uid);

        if ($user === null) {
            DomainError::ResetTokenInvalid->throw();
        }

        $success = $this->broker->reset($user, $token, $newPassword);

        if (! $success) {
            DomainError::ResetTokenInvalid->throw();
        }
    }
}
