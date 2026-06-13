## 1. Token issuance abstraction (D1, D2 — pontos #3, #5, #7)

- [x] 1.1 Criar VO `app/Features/Authentication/Domain/ValueObjects/IssuedToken.php` (readonly: `plainTextToken`, `id`, `createdAt`)
- [x] 1.2 Criar `app/Features/Authentication/Domain/Contracts/TokenIssuerInterface.php` (`issue`, `rotate`, `revoke`, `revokeAll`) — `isStale` ficou de fora: a política de frescura vive no Service (mais fiel a "policy in domain")
- [x] 1.3 Criar `app/Features/Authentication/Infrastructure/Gateways/SanctumTokenIssuer.php` implementando o contract (única classe que toca `Laravel\Sanctum\*`; expiração de 30 dias; rotação em transação)
- [x] 1.4 Registrar binding `TokenIssuerInterface => SanctumTokenIssuer` no `AuthenticationServiceProvider`
- [x] 1.5 Encolher `AuthenticationRepositoryInterface`/`AuthenticationRepository` para `findUserById`, `findUserByEmail`, `createUser`, `updatePassword` (remover métodos de token)
- [x] 1.6 Renomear `findActiveUserByEmail` → `findUserByEmail` e atualizar todos os call sites

## 2. Domain Service desacoplado do Sanctum (#3, #6)

- [x] 2.1 Remover imports `Laravel\Sanctum\*` do `AuthenticationService`; usar `TokenIssuerInterface` e `IssuedToken`
- [x] 2.2 `signUp`/`signIn` emitem token via `TokenIssuer::issue`; `signOut` via `revoke`; `logOut` via `revokeAll`
- [x] 2.3 `rotateTokenIfStale(IssuedToken, User)` decide frescura (7 dias) no Service e delega rotação ao `TokenIssuer`
- [x] 2.4 Documentar `Hash`/Sanctum como fronteira consciente (D5) — `Hash::check` permanece no Service (ver `domain-invariants.md`)

## 3. Middleware como orquestrador (D4 — #3)

- [x] 3.1 `RotateToken` constrói `IssuedToken` a partir de `currentAccessToken()` (única ponte Http→Sanctum) e chama `rotateTokenIfStale`
- [x] 3.2 Preservar guardas: token ausente / `exists === false` → sem rotação; setar header `X-New-Token` quando houver novo token

## 4. Password reset confiável e simétrico (D3 — #1, #2, #4)

- [x] 4.1 Adicionar `createToken(User): string` a `PasswordResetBrokerInterface` e implementar em `PasswordResetBroker`
- [x] 4.2 `SendResetPasswordJob::handle` injeta `PasswordResetBrokerInterface` (remover o concreto `Illuminate\Auth\Passwords\PasswordBroker`)
- [x] 4.3 `PasswordResetBroker::reset` usa `TokenIssuer::revokeAll` para invalidar tokens pós-reset
- [x] 4.4 Adicionar `'frontend_url' => env('FRONTEND_URL')` em `config/app.php` (+ `.env.example`); garantir URL absoluta na `ResetPasswordNotification`
- [x] 4.5 Remover `use Queueable` morto de `ResetPasswordNotification` (#12)

## 5. Contrato de resposta e erro consistentes (#8, #9, #11)

- [x] 5.1 `signIn` retorna `{token, user}`; shape unificado via `AuthSessionResponse` (base de `SignUpResponse`/`SignInResponse`)
- [x] 5.2 Padronizar tipos de retorno do controller; status `201` movido para `SignUpResponse::withResponse` (sem `setStatusCode` solto)
- [x] 5.3 `DomainError::field()` exaustivo (remover braço `default`)

## 6. Rotas e documentação (#10, #13)

- [x] 6.1 Padronizar nomes de rota de sign-out: `/sign-out` (sessão atual) + `/sign-out-all` (todos os devices). Teste `LogOutTest` atualizado.
- [x] 6.2 Atualizar `.kiro/steering/architecture.md` e `domain-invariants.md`: carve-out de `User` sem soft-delete + regra de fronteira Hash/Sanctum

## 8. Classificação de patterns (D9)

- [x] 8.1 `Infrastructure/Services/` → `Infrastructure/Gateways/` (move `SanctumTokenIssuer` + `PasswordResetBroker`, atualiza namespaces e imports no provider)
- [x] 8.2 `AuthenticationRepository`/`AuthenticationRepositoryInterface` → `UserRepository`/`UserRepositoryInterface` (atualiza Service, broker, provider, teste)

## 7. Testes e verificação

- [x] 7.1 Teste que executa `SendResetPasswordJob::handle()` de verdade (`dispatchSync`, sem `Queue::fake`) — prova resolução do broker + envio único
- [x] 7.2 Teste de arquitetura: nenhum arquivo em `Domain/**` importa `Laravel\Sanctum\*`
- [x] 7.3 Regressão: testes existentes atualizados (signIn `{token,user}`, signOut/logOut, reset, non-enumeration, rotação no unit test)
- [x] 7.4 Teste do novo contrato de `signIn` (`{token, user}` + ausência de password)
- [ ] 7.5 `composer test` + `composer analyse` (PHPStan level 6) + `composer lint` verdes — **REQUER SAIL (rodar localmente)**
- [ ] 7.6 `npx openspec validate authentication-quality-fixes --strict` passa
