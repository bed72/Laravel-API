<?php

use App\Core\Domain\Exceptions\DomainException;
use App\Features\Authentication\Domain\Contracts\AuthenticationRepositoryInterface;
use App\Features\Authentication\Domain\Contracts\PasswordResetNotifierInterface;
use App\Features\Authentication\Domain\Services\AuthenticationService;
use App\Features\Users\Domain\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

uses(TestCase::class);

afterEach(fn () => Mockery::close());

function makeService(
    ?AuthenticationRepositoryInterface $repository = null,
    ?PasswordResetNotifierInterface $notifier = null,
): AuthenticationService {
    return new AuthenticationService(
        notifier: $notifier ?? Mockery::mock(PasswordResetNotifierInterface::class),
        repository: $repository ?? Mockery::mock(AuthenticationRepositoryInterface::class),
    );
}

// ─── signUp ───────────────────────────────────────────────────────────────────

it('signs up a new user and returns user with access token', function () {
    $user = new User;
    $user->id = 1;
    $user->name = 'John';
    $user->email = 'john@example.com';

    $accessToken = Mockery::mock(NewAccessToken::class);

    $repository = Mockery::mock(AuthenticationRepositoryInterface::class);
    $repository->shouldReceive('findActiveUserByEmail')
        ->once()
        ->with('john@example.com')
        ->andReturnNull();
    $repository->shouldReceive('findUserIncludingTrashed')
        ->once()
        ->with('john@example.com')
        ->andReturnNull();
    $repository->shouldReceive('createUser')
        ->once()
        ->with(['name' => 'John', 'email' => 'john@example.com', 'password' => 'secret123'])
        ->andReturn($user);
    $repository->shouldReceive('createToken')
        ->once()
        ->with($user)
        ->andReturn($accessToken);

    $result = makeService(repository: $repository)->signUp('John', 'john@example.com', 'secret123');

    expect($result['user'])->toBe($user);
    expect($result['token'])->toBe($accessToken);
});

it('throws EmailAlreadyRegistered when email exists as active user', function () {
    $repository = Mockery::mock(AuthenticationRepositoryInterface::class);
    $repository->shouldReceive('findActiveUserByEmail')
        ->once()
        ->with('existing@example.com')
        ->andReturn(new User);

    makeService(repository: $repository)->signUp('Test', 'existing@example.com', 'secret123');
})->throws(DomainException::class, 'Este e-mail já está registrado.');

it('throws EmailBelongsToDeactivated when email exists as trashed user', function () {
    $repository = Mockery::mock(AuthenticationRepositoryInterface::class);
    $repository->shouldReceive('findActiveUserByEmail')
        ->once()
        ->andReturnNull();
    $repository->shouldReceive('findUserIncludingTrashed')
        ->once()
        ->with('deactivated@example.com')
        ->andReturn(new User);

    makeService(repository: $repository)->signUp('Test', 'deactivated@example.com', 'secret123');
})->throws(DomainException::class, 'Este e-mail pertence a uma conta desativada.');

// ─── signIn ───────────────────────────────────────────────────────────────────

it('signs in with valid credentials and returns access token', function () {
    $user = new User;
    $user->setRawAttributes(['password' => 'hashed-password']);

    $accessToken = Mockery::mock(NewAccessToken::class);

    $repository = Mockery::mock(AuthenticationRepositoryInterface::class);
    $repository->shouldReceive('findActiveUserByEmail')
        ->once()
        ->with('user@example.com')
        ->andReturn($user);
    $repository->shouldReceive('createToken')
        ->once()
        ->with($user)
        ->andReturn($accessToken);

    Hash::shouldReceive('check')
        ->once()
        ->with('correct-password', 'hashed-password')
        ->andReturnTrue();

    $result = makeService(repository: $repository)->signIn('user@example.com', 'correct-password');

    expect($result)->toBe($accessToken);
});

it('throws InvalidCredentials when user does not exist', function () {
    $repository = Mockery::mock(AuthenticationRepositoryInterface::class);
    $repository->shouldReceive('findActiveUserByEmail')
        ->once()
        ->with('nonexistent@example.com')
        ->andReturnNull();
    $repository->shouldReceive('findUserIncludingTrashed')
        ->once()
        ->with('nonexistent@example.com')
        ->andReturnNull();

    makeService(repository: $repository)->signIn('nonexistent@example.com', 'password123');
})->throws(DomainException::class, 'Credenciais inválidas.');

it('throws InvalidCredentials when password is wrong', function () {
    $user = new User;
    $user->setRawAttributes(['password' => 'hashed-password']);

    $repository = Mockery::mock(AuthenticationRepositoryInterface::class);
    $repository->shouldReceive('findActiveUserByEmail')
        ->once()
        ->andReturn($user);

    Hash::shouldReceive('check')
        ->once()
        ->with('wrong-password', 'hashed-password')
        ->andReturnFalse();

    makeService(repository: $repository)->signIn('user@example.com', 'wrong-password');
})->throws(DomainException::class, 'Credenciais inválidas.');

// ─── signOut ──────────────────────────────────────────────────────────────────

it('deletes the current token on sign out', function () {
    $token = Mockery::mock(PersonalAccessToken::class);

    $repository = Mockery::mock(AuthenticationRepositoryInterface::class);
    $repository->shouldReceive('deleteToken')
        ->once()
        ->with($token);

    makeService(repository: $repository)->signOut($token);
});

// ─── logOut ───────────────────────────────────────────────────────────────────

it('deletes all tokens on log out', function () {
    $user = new User;

    $repository = Mockery::mock(AuthenticationRepositoryInterface::class);
    $repository->shouldReceive('deleteAllTokens')
        ->once()
        ->with($user);

    makeService(repository: $repository)->logOut($user);
});

// ─── requestPasswordReset ─────────────────────────────────────────────────────

it('notifies the user when email exists', function () {
    $user = new User;

    $repository = Mockery::mock(AuthenticationRepositoryInterface::class);
    $repository->shouldReceive('findActiveUserByEmail')
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
    $repository = Mockery::mock(AuthenticationRepositoryInterface::class);
    $repository->shouldReceive('findActiveUserByEmail')
        ->once()
        ->with('ghost@example.com')
        ->andReturnNull();

    $notifier = Mockery::mock(PasswordResetNotifierInterface::class);
    $notifier->shouldNotReceive('notify');

    makeService(repository: $repository, notifier: $notifier)->requestPasswordReset('ghost@example.com');
});

// ─── resetPassword ────────────────────────────────────────────────────────────

it('throws ResetTokenInvalid when user id does not exist', function () {
    $repository = Mockery::mock(AuthenticationRepositoryInterface::class);
    $repository->shouldReceive('findUserById')
        ->once()
        ->with('999')
        ->andReturnNull();

    makeService(repository: $repository)->resetPassword('999', 'some-token', 'new-password123');
})->throws(DomainException::class, 'Token de redefinição inválido.');
