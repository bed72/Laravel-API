## Why

A auditoria da feature `Authentication` (pós-`authentication-layer-fixes`) revelou um defeito que **quebra em runtime** mascarado pela suíte de testes, além de uma reforma arquitetural deixada pela metade e um conjunto de inconsistências de coerência/SOLID. O bugfix anterior fechou os 9 defeitos catalogados, mas introduziu/deixou 13 pontos que comprometem confiabilidade (envio de e-mail de reset), pureza da camada de domínio e consistência de contrato.

## What Changes

- **BREAKING (runtime fix)**: `SendResetPasswordJob` deixa de injetar o concreto `Illuminate\Auth\Passwords\PasswordBroker` (não-bindável pelo container → `BindingResolutionException`) e passa a depender de `PasswordResetBrokerInterface`. O fluxo de e-mail de reset hoje falha em produção e os testes não pegam porque `Queue::fake()` nunca executa `handle()`.
- Adicionar `config('app.frontend_url')` (hoje ausente) — o link de reset gerado está sem scheme/host.
- **Abstrair a emissão/rotação de tokens atrás de um contract de domínio** (`TokenIssuerInterface` + VO `IssuedToken`), com adapter Sanctum na Infrastructure. O `AuthenticationService` deixa de importar `Laravel\Sanctum\*`.
- Tornar simétrica a abstração do password broker: `PasswordResetBrokerInterface` ganha `createToken()`, consumido pelo Job — eliminando o vazamento do broker cru.
- Renomear `findActiveUserByEmail` → `findUserByEmail` (o "Active" é ficção remanescente do soft-delete já removido; não há filtro algum).
- Padronizar tipos de retorno e contrato de resposta do `AuthenticationController`; incluir o `user` na resposta de `signIn` (simetria com `signUp`, aditivo).
- Tornar `DomainError::field()` exaustivo (hoje usa `default =>`, perdendo a checagem de exaustividade dos demais `match`).
- Segregar `AuthenticationRepositoryInterface` (ISP); remover trait `Queueable` morto de `ResetPasswordNotification`; padronizar nomes de rota; documentar `Hash`/Sanctum como fronteira consciente.
- Atualizar steering (`architecture.md`, `domain-invariants.md`) que ainda afirma "soft-delete em tudo" — contradiz a decisão consciente de `User` sem soft-delete.

## Capabilities

### New Capabilities
- `authentication`: contrato e invariantes da feature de autenticação (sign-up/in/out, rotação de token via contract de domínio, reset de senha com entrega confiável de e-mail, envelope de erro/resposta consistente).

### Modified Capabilities
<!-- Nenhuma: o projeto migrou de .kiro/specs para OpenSpec; não há base spec prévia em openspec/specs/. Esta é a primeira captura formal da capability. -->

## Impact

- **Código**: `app/Features/Authentication/**` (Service, Repository, Contracts, Jobs, Notifications, Infrastructure/Services, Http/Controllers, Http/Responses, Providers), `app/Core/Domain/Exceptions/DomainError.php`, `config/app.php`.
- **Novos arquivos**: `Domain/Contracts/TokenIssuerInterface.php`, `Domain/ValueObjects/IssuedToken.php`, `Infrastructure/Gateways/SanctumTokenIssuer.php`.
- **Renames**: `Infrastructure/Services/` → `Infrastructure/Gateways/` (são gateways, não services); `AuthenticationRepository`/`Interface` → `UserRepository`/`Interface`.
- **Testes**: `tests/Unit/Authentication/*`, `tests/Feature/Authentication/*` (incluindo um teste que executa `SendResetPasswordJob::handle()` de verdade), novo teste de arquitetura impedindo imports de `Laravel\Sanctum\*` no Domain.
- **Docs**: `.kiro/steering/architecture.md`, `.kiro/steering/domain-invariants.md`.
- **Contrato observável**: `signIn` passa a retornar `{token, user}` (aditivo, retrocompatível). Demais endpoints inalterados.
