<?php

use App\Core\Domain\Exceptions\DomainException;
use App\Features\Authentication\Domain\Repositories\UserRepositoryInterface;
use App\Features\Authentication\Domain\Gateways\PasswordResetBrokerInterface;
use App\Features\Authentication\Domain\Gateways\PasswordResetNotifierInterface;
use App\Features\Authentication\Domain\Gateways\TokenIssuerInterface;
use App\Features\Authentication\Application\Services\AuthenticationService;
use App\Features\Authentication\Application\Data\IssuedTokenData;
use App\Features\Users\Domain\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class);

afterEach(fn () => Mockery::close());

function makeService(
    ?UserRepositoryInterface $repository = null,
    ?PasswordResetNotifierInterface $notifier = null,
    ?PasswordResetBrokerInterface $broker = null,
    ?TokenIssuerInterface $tokenIssuer = null,
): AuthenticationService {
    return new AuthenticationService(
        notifier: $notifier ?? Mockery::mock(PasswordResetNotifierInterface::class),
        repository: $repository ?? Mockery::mock(UserRepositoryInterface::class),
        broker: $broker ?? Mockery::mock(PasswordResetBrokerInterface::class),
        tokenIssuer: $tokenIssuer ?? Mockery::mock(TokenIssuerInterface::class),
    );
}

function fakeIssuedToken(string $plainText = 'plain-text-token'): IssuedTokenData
{
    return new IssuedTokenData(id: 1, createdAt: now(), plainTextToken: $plainText);
}

// ─── signUp ───────────────────────────────────────────────────────────────────

it('signs up a new user and returns user with access token', function () {
    $user = new User;
    $user->id = 1;
    $user->name = 'John';
    $user->email = 'john@example.com';

    $token = fakeIssuedToken();

    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findUserByEmail')
        ->once()
        ->with('john@example.com')
        ->andReturnNull();
    $repository->shouldReceive('createUser')
        ->once()
        ->with('John', 'john@example.com', 'secret123')
        ->andReturn($user);

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $tokenIssuer->shouldReceive('issue')
        ->once()
        ->with($user, 30)
        ->andReturn($token);

    $result = makeService(repository: $repository, tokenIssuer: $tokenIssuer)
        ->signUp('John', 'john@example.com', 'secret123');

    expect($result->user)->toBe($user);
    expect($result->token)->toBe($token);
});

it('throws EmailAlreadyRegistered when email exists as active user', function () {
    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findUserByEmail')
        ->once()
        ->with('existing@example.com')
        ->andReturn(new User);

    makeService(repository: $repository)->signUp('Test', 'existing@example.com', 'secret123');
})->throws(DomainException::class, 'Este e-mail já está registrado.');

// ─── signIn ───────────────────────────────────────────────────────────────────

it('signs in with valid credentials and returns user with access token', function () {
    $user = new User;
    $user->setRawAttributes(['password' => 'hashed-password']);

    $token = fakeIssuedToken();

    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findUserByEmail')
        ->once()
        ->with('user@example.com')
        ->andReturn($user);

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $tokenIssuer->shouldReceive('issue')
        ->once()
        ->with($user, 30)
        ->andReturn($token);

    Hash::shouldReceive('check')
        ->once()
        ->with('correct-password', 'hashed-password')
        ->andReturnTrue();

    $result = makeService(repository: $repository, tokenIssuer: $tokenIssuer)
        ->signIn('user@example.com', 'correct-password');

    expect($result->user)->toBe($user);
    expect($result->token)->toBe($token);
});

it('throws InvalidCredentials when user does not exist', function () {
    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findUserByEmail')
        ->once()
        ->with('nonexistent@example.com')
        ->andReturnNull();

    makeService(repository: $repository)->signIn('nonexistent@example.com', 'password123');
})->throws(DomainException::class, 'Credenciais inválidas.');

it('throws InvalidCredentials when password is wrong', function () {
    $user = new User;
    $user->setRawAttributes(['password' => 'hashed-password']);

    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findUserByEmail')
        ->once()
        ->andReturn($user);

    Hash::shouldReceive('check')
        ->once()
        ->with('wrong-password', 'hashed-password')
        ->andReturnFalse();

    makeService(repository: $repository)->signIn('user@example.com', 'wrong-password');
})->throws(DomainException::class, 'Credenciais inválidas.');

// ─── signOut ──────────────────────────────────────────────────────────────────

it('revokes the current token on sign out', function () {
    $token = fakeIssuedToken();

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $tokenIssuer->shouldReceive('revoke')
        ->once()
        ->with($token);

    makeService(tokenIssuer: $tokenIssuer)->signOut($token);
});

// ─── logOut ───────────────────────────────────────────────────────────────────

it('revokes all tokens on log out', function () {
    $user = new User;

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $tokenIssuer->shouldReceive('revokeAll')
        ->once()
        ->with($user);

    makeService(tokenIssuer: $tokenIssuer)->logOut($user);
});

// ─── rotateTokenIfStale ─────────────────────────────────────────────────────────

it('does not rotate a fresh token', function () {
    $fresh = new IssuedTokenData(id: 1, createdAt: now()->subDays(2));

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $tokenIssuer->shouldNotReceive('rotate');

    $result = makeService(tokenIssuer: $tokenIssuer)->rotateTokenIfStale($fresh, new User);

    expect($result)->toBeNull();
});

it('rotates a stale token', function () {
    $user = new User;
    $stale = new IssuedTokenData(id: 1, createdAt: now()->subDays(10));
    $rotated = fakeIssuedToken('rotated-token');

    $tokenIssuer = Mockery::mock(TokenIssuerInterface::class);
    $tokenIssuer->shouldReceive('rotate')
        ->once()
        ->with($stale, $user, 30)
        ->andReturn($rotated);

    $result = makeService(tokenIssuer: $tokenIssuer)->rotateTokenIfStale($stale, $user);

    expect($result)->toBe($rotated);
});

// ─── requestPasswordReset ─────────────────────────────────────────────────────

it('notifies the user when email exists', function () {
    $user = new User;

    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findUserByEmail')
        ->once()
        ->with('user@example.com')
        ->andReturn($user);

    $notifier = Mockery::mock(PasswordResetNotifierInterface::class);
    $notifier->shouldReceive('notify')
        ->once()
        ->with($user);

    makeService(repository: $repository, notifier: $notifier)->requestPasswordReset('user@example.com');
});

it('does not notify when email does not exist', function () {
    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findUserByEmail')
        ->once()
        ->with('ghost@example.com')
        ->andReturnNull();

    $notifier = Mockery::mock(PasswordResetNotifierInterface::class);
    $notifier->shouldNotReceive('notify');

    makeService(repository: $repository, notifier: $notifier)->requestPasswordReset('ghost@example.com');
});

// ─── resetPassword ────────────────────────────────────────────────────────────

it('throws ResetTokenInvalid when user id does not exist', function () {
    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findUserById')
        ->once()
        ->with('999')
        ->andReturnNull();

    makeService(repository: $repository)->resetPassword('999', 'some-token', 'new-password123');
})->throws(DomainException::class, 'Token de redefinição inválido.');

it('resets password successfully when broker returns true', function () {
    $user = new User;
    $user->id = 1;
    $user->email = 'user@example.com';

    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findUserById')
        ->once()
        ->with('1')
        ->andReturn($user);

    $broker = Mockery::mock(PasswordResetBrokerInterface::class);
    $broker->shouldReceive('reset')
        ->once()
        ->with($user, 'valid-token', 'new-password123')
        ->andReturnTrue();

    makeService(repository: $repository, broker: $broker)->resetPassword('1', 'valid-token', 'new-password123');
});

it('throws ResetTokenInvalid when broker returns false', function () {
    $user = new User;
    $user->id = 1;
    $user->email = 'user@example.com';

    $repository = Mockery::mock(UserRepositoryInterface::class);
    $repository->shouldReceive('findUserById')
        ->once()
        ->with('1')
        ->andReturn($user);

    $broker = Mockery::mock(PasswordResetBrokerInterface::class);
    $broker->shouldReceive('reset')
        ->once()
        ->with($user, 'invalid-token', 'new-password123')
        ->andReturnFalse();

    makeService(repository: $repository, broker: $broker)->resetPassword('1', 'invalid-token', 'new-password123');
})->throws(DomainException::class, 'Token de redefinição inválido.');
