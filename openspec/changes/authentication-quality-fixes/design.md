## Context

A feature `Authentication` passou pelo bugfix `authentication-layer-fixes`, que fechou 9 defeitos. Uma auditoria posterior encontrou 13 pontos remanescentes/introduzidos:

1. `SendResetPasswordJob::handle(PasswordBroker $broker)` injeta o concreto `Illuminate\Auth\Passwords\PasswordBroker`, que o container não consegue construir (construtor exige `TokenRepositoryInterface`/`UserProvider` não-bindáveis) → `BindingResolutionException` em runtime. Mascarado porque todo teste de reset usa `Queue::fake()` e nunca executa `handle()`.
2. `config('app.frontend_url')` não existe → link de reset sem scheme/host.
3. `AuthenticationService` (Domain) importa `Laravel\Sanctum\NewAccessToken`/`PersonalAccessToken` e lê `$token->created_at` — infra no Domain. Mesma classe de violação que o bugfix dizia ter corrigido (abstraiu `Password::broker()` mas não os tokens).
4. Abstração do broker assimétrica: reset passa por `PasswordResetBrokerInterface`, mas a criação do token vaza crua no Job.
5. ISP: `AuthenticationRepositoryInterface` com 8 métodos; `PasswordResetBroker` usa 2.
6. `Hash::check` (facade) direto no Domain Service.
7. `findActiveUserByEmail` — "Active" é ficção do soft-delete já removido; sem filtro algum.
8. Contrato assimétrico: `signUp` retorna `user`, `signIn` só `token`.
9. Tipos de retorno do controller misturados (JsonResponse / JsonResource), `201` setado na mão.
10. Naming de rota inconsistente (`/sign-out` kebab vs `/logout`).
11. `DomainError::field()` usa `default =>`, perdendo exaustividade.
12. `ResetPasswordNotification` com `use Queueable` morto (não implementa `ShouldQueue`).
13. Steering (`architecture.md`/`domain-invariants.md`) ainda afirma "soft-delete em tudo", contradizendo `User` sem soft-delete.

Constraint: a arquitetura é vertical-slice em camadas `Http → Domain (Service/Contracts) → Infrastructure (Repositories/Adapters)`. O contrato observável deve ser preservado, exceto a adição retrocompatível de `user` em `signIn`.

## Goals / Non-Goals

**Goals:**
- Corrigir o envio de e-mail de reset (resolução de DI + URL absoluta) com teste que execute `handle()` de verdade.
- Remover o acoplamento do Domain ao Sanctum via um contract de domínio para o ciclo de vida do token.
- Tornar a abstração do password broker simétrica (criação e reset pela mesma interface).
- Eliminar inconsistências de naming, contrato de resposta, ISP e exaustividade de enum.
- Alinhar a documentação de steering à realidade (User sem soft-delete).

**Non-Goals:**
- JWT real (access 15min + refresh 30d + blacklist) — continua escopo de feature separada.
- Soft-delete no `User` — decisão mantida: não existe.
- Modelar um aggregate/tabela de token próprio — Sanctum permanece o token store.

## Decisions

### D1 — Ciclo de vida do token vira um contract de domínio, não um Repository
Cria-se `TokenIssuerInterface` (Domain) como dono do ciclo de vida do token, e um VO de domínio `IssuedToken` (carrega `plainTextToken`, `id`, `createdAt`):

```php
interface TokenIssuerInterface
{
    public function issue(User $user): IssuedToken;
    public function isStale(IssuedToken $token): bool;     // política de 7 dias
    public function rotate(IssuedToken $token, User $user): IssuedToken;
    public function revoke(IssuedToken $token): void;       // sign-out
    public function revokeAll(User $user): void;            // log-out / pós-reset
}
```

Impl `SanctumTokenIssuer` (Infrastructure) embrulha Sanctum (`createToken` com `expiresAt = now()->addDays(30)`, delete por id, etc.).

**Por que não um `TokenRepository`:** Sanctum já é o token store; um repositório só recriaria o mesmo acoplamento com outro nome. O que falta é abstrair *emissão/ciclo de vida*, não *persistência de um aggregate nosso*. Isso também dissolve o ISP (#5): o `AuthenticationRepository` encolhe para persistência de `User`.

**Alternativa considerada:** aceitar Sanctum como primitiva de domínio + documentar (menor churn). Rejeitada pelo usuário em favor da pureza/portabilidade total.

### D2 — `AuthenticationRepository` encolhe para `User` apenas
Métodos finais: `findUserById`, `findUserByEmail`, `createUser`, `updatePassword`. Os métodos de token (`createToken`/`deleteToken`/`deleteAllTokens`/`rotateToken`) migram para `SanctumTokenIssuer`. Resolve #5 (ISP) e #7 (rename).

### D3 — Broker de reset simétrico, e fim do DI quebrado (#1, #4)
`PasswordResetBrokerInterface` ganha `createToken(User): string` além de `reset(...)`. O `SendResetPasswordJob` passa a injetar `PasswordResetBrokerInterface` (bindado no provider, logo resolvível) em vez do concreto `PasswordBroker`. Uma única mudança elimina o `BindingResolutionException` **e** a assimetria de abstração.

### D4 — Middleware constrói o VO; Domain decide (#3)
`RotateToken` (Http) é a fronteira que pode tocar Sanctum: lê `currentAccessToken()` e constrói um `IssuedToken`. Passa o VO ao `AuthenticationService::rotateTokenIfStale(IssuedToken, User)`, que delega a `TokenIssuer`. O middleware segue só orquestrando request/response e setando o header `X-New-Token`.

### D5 — `Hash` permanece no Domain, como fronteira declarada (#6)
`Hash::check` é utility stateless (sem estado a abstrair). Mantém-se no Service, mas a regra fica **explícita** no steering: abstraímos brokers/recursos *com estado* (`Password`, tokens); utilities stateless (`Hash`) são aceitas no Domain. Remove a arbitrariedade, não o uso.

### D6 — Contrato de resposta consistente (#8, #9)
- `signIn` passa a retornar `{token, user}` (aditivo). Cria-se `AuthTokenResponse`/reuso de shape para `signUp` e `signIn` retornarem o mesmo envelope.
- Padroniza-se o estilo: respostas com payload via JsonResource + `HttpStatusCode`; respostas sem payload via 204. Sem `setStatusCode(201)` solto — o 201 fica no Response.

### D7 — `DomainError::field()` exaustivo (#11)
Substituir `default => null` por braço explícito de cada case (`InvalidCredentials`/`ResetTokenInvalid => null`), preservando a checagem de exaustividade do compilador/PHPStan.

### D8 — Itens de higiene
- #10: padronizar rotas — `/sign-out` (sessão atual) + `/sign-out-all` (todos os devices).
- #12: remover `use Queueable` de `ResetPasswordNotification` (no-op; já roda dentro de job assíncrono).
- #13: atualizar `architecture.md`/`domain-invariants.md` para carve-out explícito de `User` sem soft-delete.

### D9 — Classificação correta: Gateways, não Repositories
`TokenIssuerInterface` e `PasswordResetBrokerInterface` **não são repositórios** — são **gateways**. O critério não é "toca dado" (quase tudo toca), e sim: Repository = coleção dos *seus aggregates*, retorna *suas entidades*; Gateway = embrulho de um *mecanismo/subsistema externo* (Sanctum, `Password::broker()`), retorna credencial/outcome. O verbo dominante (`issue`, `reset`) e o retorno (VO write-once / `bool`) confirmam: gateway.

Consequências de organização:
- `Infrastructure/Services/` → **`Infrastructure/Gateways/`** (abriga `SanctumTokenIssuer` + `PasswordResetBroker`). O nome "Services" era balde genérico herdado; não há domain service na Infra.
- `AuthenticationRepository`/`AuthenticationRepositoryInterface` → **`UserRepository`/`UserRepositoryInterface`** (é o único repo de verdade — retorna `User`; o nome agora reflete o aggregate).
- **Não** criar pastas por estereótipo (`Issuer/`, `Broker/`, `Notifier/`): seriam single-file folders e o sufixo da classe já carrega o pattern. Pasta se paga com volume + coesão, não com nome de pattern.
- Quando vier o JWT real com blacklist, **aí** nasce um repositório de verdade (`TokenBlacklistRepository` — aggregate nosso) ao lado do issuer.

## Risks / Trade-offs

- **Over-engineering do `TokenIssuer`** → mitigação: contract enxuto (5 métodos), VO sem comportamento; o ganho é remover Sanctum do Domain e dissolver o repo fat, não criar camadas extras.
- **Adicionar `user` ao `signIn` muda o payload** → aditivo e retrocompatível; clientes que ignoram campos extras não quebram. Mitigação: documentar no changelog.
- **`isStale` depende de `createdAt` no VO** → o middleware precisa popular `createdAt` a partir do token Sanctum corrente; mitigação: `SanctumTokenIssuer` é o único lugar que lê `PersonalAccessToken`, mantendo o vazamento confinado à Infra/Http.
- **Teste real do Job** pode exigir `Mail::fake()`/`Notification::fake()` em vez de `Queue::fake()` para exercitar `handle()` → mitigação: novo teste dedicado processando o job sincronamente.

## Migration Plan

1. Introduzir `IssuedToken` + `TokenIssuerInterface` + `SanctumTokenIssuer`; bindar no provider.
2. Migrar Service/middleware para o contract; remover imports Sanctum do Domain.
3. Encolher `AuthenticationRepository` e renomear `findUserByEmail`; atualizar chamadas e testes.
4. Ampliar `PasswordResetBrokerInterface` (`createToken`) e re-apontar o Job para a interface; adicionar `app.frontend_url`.
5. Ajustar respostas (`signIn` com user, 204 mantidos), `DomainError::field()`, rotas, notification.
6. Atualizar steering docs.
7. Rodar `composer test` + `analyse` + novo teste de arquitetura (sem Sanctum no Domain) + teste real do Job.

Rollback: a change é um conjunto de refactors atrás dos mesmos endpoints; reverter o commit restaura o estado anterior sem migração de dados (nenhuma migration de schema envolvida).

## Open Questions

- Naming final das rotas (#10): manter `/logout` por familiaridade ou padronizar para `/sign-out-all`? (default proposto: `/sign-out-all`, confirmar.)
- `signIn` retornando `user` precisa de confirmação de produto/contrato do front, ou seguimos com o aditivo? (default proposto: seguir, por simetria.)
