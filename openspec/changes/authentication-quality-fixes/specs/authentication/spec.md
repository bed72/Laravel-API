## ADDED Requirements

### Requirement: Reliable password reset email delivery

O sistema SHALL entregar o e-mail de redefinição de senha sem erros de runtime quando um e-mail registrado solicita reset. O `SendResetPasswordJob` SHALL depender de `PasswordResetBrokerInterface` (resolvível pelo container via binding da feature), e NÃO SHALL depender do concreto `Illuminate\Auth\Passwords\PasswordBroker` (não-bindável, que causa `BindingResolutionException`). A criação do token de reset SHALL passar pela mesma abstração de broker usada na redefinição, mantendo a abstração simétrica.

#### Scenario: Job executa e envia exatamente um e-mail
- **WHEN** o `SendResetPasswordJob` é processado pela fila para um usuário registrado
- **THEN** o broker de reset é resolvido pelo container sem exceção de binding
- **AND** exatamente uma notificação `ResetPasswordNotification` é enviada ao usuário

#### Scenario: Re-dispatch durante a janela de unicidade não duplica
- **WHEN** um segundo `SendResetPasswordJob` para o mesmo usuário é despachado dentro de `uniqueFor`
- **THEN** o job é deduplicado e apenas um e-mail é enviado

### Requirement: Reset link uses absolute frontend URL

O link de redefinição enviado no e-mail SHALL ser uma URL absoluta contendo scheme e host derivados de uma configuração `app.frontend_url`. O sistema NÃO SHALL gerar um link relativo (sem host) por configuração ausente.

#### Scenario: URL absoluta no e-mail
- **WHEN** a `ResetPasswordNotification` monta o link de reset
- **THEN** a URL começa com o valor configurado de `app.frontend_url` (scheme + host)
- **AND** inclui o `uid` e o token de reset no caminho

### Requirement: Token lifecycle is abstracted behind a domain contract

A emissão, verificação de frescura, rotação e revogação de tokens SHALL ser expostas por um contract de domínio (`TokenIssuerInterface`) que retorna/recebe um value object de domínio, não tipos do framework. O `AuthenticationService` NÃO SHALL importar nem referenciar `Laravel\Sanctum\*`. A camada `Domain` SHALL estar livre de dependências diretas de infraestrutura de tokens.

#### Scenario: Domain não referencia Sanctum
- **WHEN** o código da camada `app/Features/Authentication/Domain/**` é analisado
- **THEN** nenhum arquivo importa `Laravel\Sanctum\NewAccessToken` ou `Laravel\Sanctum\PersonalAccessToken`

#### Scenario: Emissão de token via contract
- **WHEN** `signUp` ou `signIn` concluem com sucesso
- **THEN** o token é emitido através de `TokenIssuerInterface`
- **AND** retornado ao cliente como string de texto plano

### Requirement: Token rotation policy preserved

Um token autenticado SHALL ser rotacionado quando ultrapassar a janela de frescura (7 dias), emitindo um novo token e invalidando o anterior. Tokens dentro da janela SHALL permanecer válidos. A política (frescura e expiração) SHALL viver na camada de domínio, não no middleware HTTP, que SHALL apenas orquestrar request/response.

#### Scenario: Token velho é rotacionado
- **WHEN** uma requisição autenticada usa um token com mais de 7 dias
- **THEN** o response inclui o header `X-New-Token` com o novo token
- **AND** o token anterior é invalidado

#### Scenario: Token fresco não rotaciona
- **WHEN** uma requisição autenticada usa um token com menos de 7 dias
- **THEN** o response não inclui o header `X-New-Token`
- **AND** o token original continua válido

#### Scenario: Token revogado na mesma requisição não rotaciona
- **WHEN** a requisição é sign-out/log-out e o token corrente foi removido durante o processamento
- **THEN** nenhuma rotação é tentada e nenhum header `X-New-Token` é emitido

### Requirement: Password reset persists via repository without infra facades in Domain

A redefinição de senha SHALL delegar a persistência (nova senha + revogação de todos os tokens) à camada de Infrastructure através de contracts. O `AuthenticationService` NÃO SHALL chamar `Password::broker()`, `$user->save()`, nem outras facades/persistência diretamente.

#### Scenario: Reset com token válido
- **WHEN** `resetPassword` é chamado com `uid` e token válidos
- **THEN** a senha do usuário é atualizada (hashed)
- **AND** todos os tokens existentes do usuário são revogados
- **AND** o endpoint responde 204 sem corpo

#### Scenario: Reset com token inválido
- **WHEN** `resetPassword` é chamado com token inválido ou `uid` inexistente
- **THEN** o sistema dispara `DomainError::ResetTokenInvalid` com HTTP 400

### Requirement: User lookup reflects persistence reality (no soft-delete fiction)

Os métodos de busca de usuário SHALL ter nomes que refletem a persistência real. Como `User` não possui soft-delete, NÃO SHALL existir método cujo nome implique distinção "active/trashed" (ex.: `findActiveUserByEmail`); a busca por e-mail SHALL chamar-se `findUserByEmail` e retornar qualquer usuário com o e-mail informado.

#### Scenario: Busca por e-mail existente
- **WHEN** `findUserByEmail` é chamado com um e-mail cadastrado
- **THEN** o usuário correspondente é retornado, sem filtro de soft-delete

### Requirement: Consistent HTTP response contract

Os endpoints de autenticação SHALL usar um contrato de resposta consistente: operações de sucesso sem payload retornam HTTP 204 sem corpo; respostas com payload usam classes Response (JsonResource) com status definido via `HttpStatusCode`. As respostas de `signUp` e `signIn` SHALL ser simétricas, ambas retornando `{token, user}`.

#### Scenario: Sign-in retorna usuário e token
- **WHEN** `signIn` conclui com credenciais válidas
- **THEN** a resposta contém `token` e o objeto `user` (`id`, `name`, `email`)

#### Scenario: Operações sem payload retornam 204
- **WHEN** `signOut`, `logOut`, `requestPasswordReset` ou `resetPassword` concluem com sucesso
- **THEN** a resposta é HTTP 204 sem corpo

### Requirement: Domain error enum is exhaustive and runtime-dispatchable

O enum `DomainError` SHALL conter apenas cases efetivamente disparáveis em runtime, e todos os seus métodos de mapeamento (`status`, `message`, `field`) SHALL tratar os cases de forma exaustiva, sem braço `default` que mascare a adição de novos cases.

#### Scenario: field() é exaustivo
- **WHEN** um novo case é adicionado ao enum `DomainError`
- **THEN** o método `field()` exige tratamento explícito do novo case (sem fallback `default` silencioso)
