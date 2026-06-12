# Design Técnico — Authentication Layer Fixes

## Visão Geral

Este design detalha as mudanças necessárias para corrigir os 9 defeitos identificados na auditoria da feature `Authentication`. As mudanças preservam o contrato observável (mesmos endpoints, mesmos status codes — exceto a padronização para 204), sem adicionar funcionalidades novas.

Decisão arquitetural importante: **não implementaremos soft-delete no User** neste bugfix. A ficção será removida em favor da realidade atual (Sanctum PAT sem soft-delete). A questão JWT vs Sanctum-PAT será documentada como decisão consciente, mas a implementação continuará usando Sanctum-PAT — mudar para JWT real é escopo de feature, não bugfix.

## Componentes Afetados

### Arquivos Modificados

| Arquivo | Mudança |
|---------|---------|
| `app/Core/Domain/Exceptions/DomainError.php` | Remover cases `EmailBelongsToDeactivated` e `PasswordWeak` |
| `app/Features/Authentication/Domain/Contracts/AuthenticationRepositoryInterface.php` | Remover `findUserIncludingTrashed`; adicionar `updatePassword(User, string): void` |
| `app/Features/Authentication/Domain/Services/AuthenticationService.php` | Remover branch soft-delete, query descartada no signIn, parâmetro `$user` no signOut; injetar `PasswordResetBrokerInterface`; adicionar `rotateTokenIfStale()` |
| `app/Features/Authentication/Infrastructure/Repositories/AuthenticationRepository.php` | Remover `findUserIncludingTrashed`; `rotateToken` delega a `createToken`; adicionar `updatePassword()` |
| `app/Features/Authentication/Http/Middleware/RotateToken.php` | Substituir lógica de domínio por chamada ao Service |
| `app/Features/Authentication/Http/Controllers/AuthenticationController.php` | Remover `$user` do signOut; retornar 204 nos endpoints de password reset |
| `app/Features/Authentication/Infrastructure/Jobs/SendResetPasswordJob.php` | Adicionar `ShouldBeUnique` + `uniqueId()` para idempotência |
| `app/Features/Authentication/Infrastructure/Providers/AuthenticationServiceProvider.php` | Registrar binding de `PasswordResetBrokerInterface` |

### Arquivos Criados

| Arquivo | Propósito |
|---------|-----------|
| `app/Features/Authentication/Domain/Contracts/PasswordResetBrokerInterface.php` | Contrato de domínio para reset de senha (abstrair `Password::broker()`) |
| `app/Features/Authentication/Infrastructure/Services/PasswordResetBroker.php` | Implementação que wrapa `Password::broker()` do Laravel |

### Arquivos Removidos

Nenhum. Apenas código morto dentro dos arquivos existentes.

---

## Design Detalhado

### 1. Remoção da ficção de Soft-Delete (Requisitos 2.1, 2.2, 2.9)

**DomainError enum** — remover dois cases:

```php
// REMOVER:
case EmailBelongsToDeactivated = 'email_belongs_to_deactivated';
case PasswordWeak = 'password_weak';

// Atualizar match arms em status(), message(), field() para remover os cases
```

**AuthenticationRepositoryInterface** — remover método:

```php
// REMOVER:
public function findUserIncludingTrashed(string $email): ?User;
```

**AuthenticationRepository** — remover implementação de `findUserIncludingTrashed`.

**AuthenticationService::signUp** — simplificar:

```php
public function signUp(string $name, string $email, string $password): array
{
    $existing = $this->repository->findActiveUserByEmail($email);

    if ($existing !== null) {
        DomainError::EmailAlreadyRegistered->throw();
    }

    // Removido: branch findUserIncludingTrashed + EmailBelongsToDeactivated

    $user = $this->repository->createUser([...]);
    $accessToken = $this->repository->createToken($user);

    return ['user' => $user, 'token' => $accessToken];
}
```

**AuthenticationService::signIn** — remover query descartada:

```php
public function signIn(string $email, string $password): NewAccessToken
{
    $user = $this->repository->findActiveUserByEmail($email);

    if ($user === null) {
        DomainError::InvalidCredentials->throw();
    }

    // Removido: findUserIncludingTrashed (resultado descartado)

    if (! Hash::check($password, $user->password)) {
        DomainError::InvalidCredentials->throw();
    }

    return $this->repository->createToken($user);
}
```

> Nota: `Hash::check` permanece no Service por enquanto. É uma utility stateless (não persiste), diferente do `Password::broker()` que gerencia estado. Abstrair Hash seria over-engineering para este bugfix.

---

### 2. Mover lógica de rotação para o Service (Requisito 2.3)

**AuthenticationService** — novo método:

```php
private const TOKEN_ROTATION_DAYS = 7;

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
```

**RotateToken middleware** — simplificado para orquestrar apenas:

```php
class RotateToken
{
    public function __construct(
        private readonly AuthenticationService $service,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return $response;
        }

        if ($token->exists === false) {
            return $response;
        }

        $newToken = $this->service->rotateTokenIfStale($token, $request->user());

        if ($newToken !== null) {
            $response->headers->set('X-New-Token', $newToken->plainTextToken);
        }

        return $response;
    }
}
```

**AuthenticationRepository::rotateToken** — delegar a `createToken`:

```php
public function rotateToken(PersonalAccessToken $oldToken, User $user): NewAccessToken
{
    return DB::transaction(function () use ($oldToken, $user): NewAccessToken {
        $oldToken->delete();

        return $this->createToken($user);
    });
}
```

---

### 3. Abstrair Password::broker() (Requisito 2.4)

**Novo contrato** — `Domain/Contracts/PasswordResetBrokerInterface.php`:

```php
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
```

**Nova implementação** — `Infrastructure/Services/PasswordResetBroker.php`:

```php
<?php

namespace App\Features\Authentication\Infrastructure\Services;

use App\Features\Authentication\Domain\Contracts\AuthenticationRepositoryInterface;
use App\Features\Authentication\Domain\Contracts\PasswordResetBrokerInterface;
use App\Features\Users\Domain\Models\User;
use Illuminate\Support\Facades\Password;

class PasswordResetBroker implements PasswordResetBrokerInterface
{
    public function __construct(
        private readonly AuthenticationRepositoryInterface $repository,
    ) {}

    public function reset(User $user, string $token, string $newPassword): bool
    {
        $status = Password::broker()->reset(
            [
                'email' => $user->email,
                'token' => $token,
                'password' => $newPassword,
            ],
            function (User $user) use ($newPassword): void {
                $this->repository->updatePassword($user, $newPassword);
                $this->repository->deleteAllTokens($user);
            },
        );

        return $status === Password::PASSWORD_RESET;
    }
}
```

**AuthenticationRepositoryInterface** — adicionar:

```php
public function updatePassword(User $user, string $newPassword): void;
```

**AuthenticationRepository** — implementar:

```php
public function updatePassword(User $user, string $newPassword): void
{
    $user->password = $newPassword;
    $user->save();
}
```

**AuthenticationService::resetPassword** — simplificado:

```php
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
```

**AuthenticationService constructor** — adicionar dependência:

```php
public function __construct(
    private readonly PasswordResetNotifierInterface $notifier,
    private readonly AuthenticationRepositoryInterface $repository,
    private readonly PasswordResetBrokerInterface $broker,
) {}
```

**AuthenticationServiceProvider** — registrar binding:

```php
public array $bindings = [
    AuthenticationRepositoryInterface::class => AuthenticationRepository::class,
    PasswordResetNotifierInterface::class => PasswordResetNotifier::class,
    PasswordResetBrokerInterface::class => PasswordResetBroker::class,
];
```

---

### 4. Remover parâmetro `$user` de `signOut` (Requisito 2.5)

**AuthenticationService::signOut** — assinatura já está correta (sem `$user`) no código atual. O controller já passa apenas o token. Verificar e confirmar que não há drift.

Confirmado pelo código lido: a assinatura atual é `signOut(PersonalAccessToken $token): void`. O controller passa `$token = $request->user()->currentAccessToken()` sem passar `$user`. **Nenhuma mudança necessária aqui** — a auditoria citou um estado anterior que já foi corrigido.

---

### 5. Padronizar respostas 204 (Requisito 2.6)

**AuthenticationController** — alterar `requestPasswordReset` e `resetPassword`:

```php
public function requestPasswordReset(PasswordResetRequest $request): JsonResponse
{
    $this->service->requestPasswordReset($request->validated('email'));

    return response()->json(null, HttpStatusCode::NoContent->value);
}

public function resetPassword(PasswordResetConfirmRequest $request): JsonResponse
{
    $this->service->resetPassword(
        uid: $request->validated('uid'),
        token: $request->validated('token'),
        newPassword: $request->validated('new_password'),
    );

    return response()->json(null, HttpStatusCode::NoContent->value);
}
```

---

### 6. Idempotência do SendResetPasswordJob (Requisito 2.7)

Usar `ShouldBeUnique` do Laravel com `uniqueId` baseado no user ID. Isso garante que, enquanto o job estiver na fila ou em execução, um retry ou re-dispatch não criará duplicata.

```php
<?php

namespace App\Features\Authentication\Infrastructure\Jobs;

use App\Features\Authentication\Infrastructure\Notifications\ResetPasswordNotification;
use App\Features\Users\Domain\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
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
        return 'password-reset-' . $this->user->id;
    }

    /**
     * Lock de unicidade expira em 5 minutos (tempo razoável para envio).
     */
    public int $uniqueFor = 300;

    public function handle(PasswordBroker $broker): void
    {
        $token = $broker->createToken($this->user);

        $this->user->notify(new ResetPasswordNotification($token));
    }
}
```

---

### 7. Eliminar duplicação em `rotateToken` (Requisito 2.8)

Já endereçado no item 2: `rotateToken` passa a delegar a `createToken` ao invés de hardcodar valores.

---

## Decisões Não Implementadas (Fora de Escopo)

| Item | Razão |
|------|-------|
| JWT real (access 15min + refresh 30d + blacklist) | Feature nova, não bugfix. Requer mudança de contrato observável, nova migration (blacklist table), nova lógica de refresh. Deve ser tratado como spec separada. |
| Soft-delete no User | Decisão: removemos a ficção. Se soft-delete for necessário no futuro, será uma feature com migration, trait, e mudança de comportamento. |
| Renomear Request classes (item 9 da auditoria) | Os nomes atuais (`SignUpRequest`, `PasswordResetRequest`, `PasswordResetConfirmRequest`) são suficientemente claros. `SignUpRequest` já casa com `signUp`. `PasswordResetRequest` → `requestPasswordReset` é uma convenção aceitável (Request nomeia o recurso, Controller nomeia a ação). Não vale o churn. |
| `UserResponse` reutilizável | Não há segunda rota que retorne user ainda. Premature abstraction — criar quando houver necessidade real. |
| `SignInResponse` retornar user | Requer confirmação de spec/produto. Fora de escopo do bugfix. |

---

## Impacto em Testes

Os testes existentes de Authentication devem ser atualizados para refletir:

1. **Respostas 204** nos endpoints `requestPasswordReset` e `resetPassword` (antes 200 com `{}`)
2. **Remoção da injeção direta** do repository no middleware (mock do Service ao invés do Repository)
3. **Novo binding** de `PasswordResetBrokerInterface` disponível no container

Testes de regressão devem cobrir todas as cláusulas 3.1–3.11 do documento de requisitos.

---

## Diagrama de Dependências (Pós-Correção)

```
Controller
    ↓
AuthenticationService
    ├── AuthenticationRepositoryInterface  (Domain contract)
    ├── PasswordResetNotifierInterface     (Domain contract)
    └── PasswordResetBrokerInterface       (Domain contract — NOVO)
         ↓
Infrastructure/
    ├── AuthenticationRepository           (implementa repo interface)
    ├── PasswordResetNotifier              (implementa notifier interface)
    │       └── SendResetPasswordJob       (ShouldBeUnique)
    └── PasswordResetBroker                (implementa broker interface — NOVO)

RotateToken (middleware)
    └── AuthenticationService              (delega decisão de rotação)
```

O fluxo Http → Repository direto é eliminado. Todo acesso ao domínio passa pelo Service.
