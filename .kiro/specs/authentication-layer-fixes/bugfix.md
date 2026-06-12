# Documento de Requisitos de Bugfix

## Introdução

A feature de Authentication (`app/Features/Authentication/`) apresenta múltiplos defeitos estruturais identificados em auditoria: código morto baseado em soft-delete inexistente, lógica de negócio na camada HTTP (middleware), dependência de infraestrutura no Domain Service, parâmetros não utilizados, convenções de resposta inconsistentes, job não idempotente e redundância na configuração de expiração de tokens. Estes defeitos combinados comprometem a manutenibilidade, testabilidade e a integridade da arquitetura Controller → Service → Repository.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN um usuário tenta se registrar com um e-mail já existente THEN o sistema executa `findUserIncludingTrashed` que é byte-identical a `findActiveUserByEmail`, resultando em código morto — o branch `EmailBelongsToDeactivated` nunca é alcançado pois o Model `User` não possui trait `SoftDeletes` nem coluna `deleted_at`

1.2 WHEN um usuário tenta fazer sign-in com credenciais inválidas THEN o sistema chama `findUserIncludingTrashed` cujo resultado é descartado, executando uma query desnecessária e idêntica à anterior

1.3 WHEN um token autenticado tem mais de 7 dias THEN a lógica de rotação é executada diretamente no middleware `RotateToken`, que injeta `AuthenticationRepositoryInterface` violando a arquitetura Controller → Service → Repository (middleware acessa Repository diretamente, ignorando a Service layer)

1.4 WHEN o método `resetPassword` é chamado no `AuthenticationService` THEN o service utiliza diretamente `Password::broker()` (facade de infraestrutura) e executa `$user->save()` inline, violando a separação Domain/Infrastructure e ignorando o Repository

1.5 WHEN `signOut` é chamado no `AuthenticationService` THEN o método recebe `User $user` como parâmetro na assinatura do Controller que nunca é utilizado (parâmetro morto)

1.6 WHEN `requestPasswordReset` ou `resetPassword` completam com sucesso THEN o controller retorna HTTP 200 com `(object) []`, enquanto `signOut` e `logOut` retornam HTTP 204 — inconsistência na convenção de respostas sem conteúdo

1.7 WHEN o `SendResetPasswordJob` sofre retry THEN cada execução gera um novo token via `$broker->createToken()` e dispara um novo e-mail, sem mecanismo de idempotência ou deduplicação

1.8 WHEN `rotateToken` é chamado no Repository THEN o método hardcoda `'api'` e `now()->addDays(30)` ao invés de reutilizar `createToken`, duplicando a lógica de criação de token

1.9 WHEN o `DomainError` enum é carregado THEN os cases `EmailBelongsToDeactivated` e `PasswordWeak` existem mas nunca são disparados em runtime (o `PasswordWeak` é coberto pela validação do FormRequest, nunca chega à Service)

### Expected Behavior (Correct)

2.1 WHEN um usuário tenta se registrar com um e-mail já existente THEN o sistema SHALL utilizar apenas `findActiveUserByEmail` para verificar existência, sem branch de soft-delete; o método `findUserIncludingTrashed` e o case `EmailBelongsToDeactivated` SHALL ser removidos

2.2 WHEN um usuário tenta fazer sign-in com credenciais inválidas THEN o sistema SHALL verificar apenas via `findActiveUserByEmail` sem chamada redundante a `findUserIncludingTrashed`

2.3 WHEN um token autenticado tem mais de 7 dias THEN o middleware SHALL delegar a decisão de rotação ao `AuthenticationService`, que encapsulará a política de rotação (7 dias) e a expiração (30 dias) como constantes de domínio; o middleware SHALL apenas orquestrar request/response

2.4 WHEN o método `resetPassword` é chamado THEN o `AuthenticationService` SHALL utilizar um `PasswordResetBrokerInterface` (contract no Domain) injetado, e a persistência SHALL ser delegada ao Repository — sem uso direto de facades de infraestrutura nem `$user->save()` no Service

2.5 WHEN `signOut` é chamado THEN a assinatura SHALL receber apenas `PersonalAccessToken $token`, sem o parâmetro `User $user` não utilizado

2.6 WHEN `requestPasswordReset` ou `resetPassword` completam com sucesso THEN o controller SHALL retornar HTTP 204 (No Content) sem body, padronizando a convenção para todas as operações de sucesso sem payload

2.7 WHEN o `SendResetPasswordJob` sofre retry THEN o job SHALL implementar um mecanismo de idempotência (e.g., verificar se já existe token válido antes de criar novo, ou utilizar `uniqueId` do Laravel para deduplicação de job) para evitar múltiplos e-mails

2.8 WHEN `rotateToken` é chamado THEN SHALL reutilizar o método `createToken` do Repository (ou extrair constantes compartilhadas) para evitar duplicação dos valores `'api'` e `30 days`

2.9 WHEN o `DomainError` enum é definido THEN SHALL conter apenas cases que são efetivamente disparáveis em runtime; `EmailBelongsToDeactivated` e `PasswordWeak` SHALL ser removidos

### Unchanged Behavior (Regression Prevention)

3.1 WHEN um usuário se registra com um e-mail nunca utilizado THEN o sistema SHALL CONTINUE TO criar o usuário, gerar token e retornar HTTP 201 com `{token, user}`

3.2 WHEN um usuário faz sign-in com credenciais válidas THEN o sistema SHALL CONTINUE TO retornar um token de acesso válido com expiração de 30 dias

3.3 WHEN um token autenticado tem menos de 7 dias THEN o sistema SHALL CONTINUE TO não rotacionar o token e processar a request normalmente

3.4 WHEN um token é rotacionado com sucesso THEN o sistema SHALL CONTINUE TO retornar o novo token no header `X-New-Token` e invalidar o token anterior

3.5 WHEN `signOut` é chamado com token válido THEN o sistema SHALL CONTINUE TO invalidar apenas o token da sessão atual e retornar HTTP 204

3.6 WHEN `logOut` é chamado THEN o sistema SHALL CONTINUE TO invalidar todos os tokens do usuário e retornar HTTP 204

3.7 WHEN `requestPasswordReset` é chamado com e-mail inexistente THEN o sistema SHALL CONTINUE TO retornar sucesso silenciosamente (non-enumeration) sem revelar se o e-mail existe

3.8 WHEN `resetPassword` é chamado com token válido THEN o sistema SHALL CONTINUE TO alterar a senha do usuário, invalidar todos os tokens existentes e confirmar sucesso

3.9 WHEN um e-mail já registrado é usado no sign-up THEN o sistema SHALL CONTINUE TO disparar `DomainError::EmailAlreadyRegistered` com HTTP 422

3.10 WHEN credenciais inválidas são usadas no sign-in THEN o sistema SHALL CONTINUE TO disparar `DomainError::InvalidCredentials` com HTTP 401

3.11 WHEN rate limiting é atingido nas rotas de sign-in ou password reset THEN o sistema SHALL CONTINUE TO retornar HTTP 429
