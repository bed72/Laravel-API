---
inclusion: auto
---

# Trocado — Arquitetura e Convenções

## Projeto

**Trocado** é uma JSON API para finanças pessoais de casais. Cada usuário possui seus próprios budgets/expenses; dois usuários podem se parear num "couple" para uma visão compartilhada, somente leitura.

O domínio é definido numa spec (Spec-Driven Development) originada numa implementação Django/DRF. Estamos **reimplementando esse domínio em Laravel 13** — replicando o _comportamento/contrato_, não o stack Django. A spec é a fonte de verdade; quando um comportamento for ambíguo, **pergunte ao invés de inventar**.

## Arquitetura: Vertical Slice

Código organizado por feature em `app/Features/<Feature>/`, não no layout flat padrão do Laravel. Cada feature tem cinco camadas na raiz do módulo (`Domain`, `Application`, `Infrastructure`, `Presentation`, `Main`):

```
app/Features/<Feature>/
├── Domain/
│   ├── Models/          # Eloquent models
│   ├── ValueObjects/    # VOs de domínio: imutáveis + auto-validados no construtor (ex.: futuros Money, Email). NÃO confundir com DTO
│   ├── Repositories/    # Ports de repositório (interfaces) — impl em Infrastructure/Repositories/
│   └── Gateways/        # Ports de gateway (interfaces) — impl em Infrastructure/Gateways/
├── Application/         # camada de caso de uso
│   ├── Services/        # Application services: orquestram o caso de uso (repos/gateways/notifiers) + regras finas
│   └── Data/            # DTOs: I/O dos casos de uso, sem invariante. Classe leva sufixo `Data` (ex.: IssuedTokenData, AuthenticationSessionData)
├── Infrastructure/
│   ├── Repositories/    # Implementações Eloquent dos ports (acesso aos aggregates próprios)
│   ├── Gateways/        # Adapters sobre mecanismos externos (Sanctum, Password::broker) — NÃO são repositories
│   ├── Notifications/   # Notifications do Laravel (mensagens) + Jobs
│   └── Jobs/
├── Presentation/        # (antigo Http) camada de entrega HTTP
│   ├── Controllers/     # Slim (injeta Service, retorna Response)
│   ├── Requests/        # FormRequest
│   ├── Responses/       # JsonResource (sufixo Response)
│   └── Middleware/
└── Main/                # composition root da feature: o que amarra/inicializa
    ├── Providers/<Feature>ServiceProvider.php   # bindings interface→impl + carrega as rotas
    └── Routes/Routes.php
```

> **Regra de dependência:** as camadas internas (`Domain`, `Application`) nunca importam concretudes
> de `Infrastructure` nem Sanctum — dependem só dos ports do domínio (`Domain/Repositories`, `Domain/Gateways`).
> O `ServiceProvider` vive em `Main/` (o composition root da feature — a única camada que pode conhecer todas
> as outras, onde as concretudes são amarradas). Um arch test (`AuthenticationArchTest`) trava Domain e
> Application contra Infrastructure/Sanctum.

**Fluxo de requisição:** Route → Controller → Service → Repository/Gateway → Model

### Service, Repository e Gateway (classificação)

- **Service** (`Application/Services`): é um **application service** — orquestra o caso de uso (busca em repos, chama gateways/notifiers, traduz falha em `DomainError`). Pode conter regras finas de negócio, mas seu papel principal é *coordenação*, não cálculo. É chamável de controller, job ou CLI sem mudança. Seus DTOs ficam ao lado, em `Application/Data/`.
- **Repository vs Gateway** — o critério **não** é "toca dado" (quase tudo toca):
  - **Repository** = coleção dos *seus aggregates*; retorna *suas entidades* (`User`, `Expense`). Trocar a impl = trocar DB/ORM.
  - **Gateway** = adapter sobre um *mecanismo externo* (Sanctum, `Password::broker()`); retorna credencial/outcome, não entidade sua. Trocar a impl = trocar o *provider*.
  - Teste de bolso: *"eu faria `->find()`/`->paginate()` e receberia uma entidade minha?"* Sim → repository; outcome/credencial → gateway.
- **Não** criar pastas por estereótipo de pattern (`Issuer/`, `Broker/`): o sufixo da classe já carrega o pattern; pasta se paga com volume + coesão.

## Wiring de Features

Cada feature tem um provider (registrado em `bootstrap/providers.php`) que:

1. Declara bindings `interface→impl` via `public array $bindings = [...]` — **sem `register()` manual**.
2. No `boot()` registra rotas via `Route::middleware('api')->prefix('api')->group(...)`.

Rotas são **descentralizadas**: cada feature possui seu `Main/Routes/Routes.php` (carregado pelo provider em `Main/`).

## Convenções de Código

- Models usam **PHP attributes** (Laravel 13): `#[Fillable([...])]` / `#[Hidden([...])]`, não `$fillable`/`$hidden`. Casts em `casts()`.
- Models fora de `app/Models` **devem** declarar factory explicitamente:
  ```php
  protected static function newFactory(): XFactory { return XFactory::new(); }
  ```
- Ownership usa `$request->user()->id` — **nunca** hardcodar user id.
- API errors renderizam como JSON para `api/*` (configurado em `bootstrap/app.php`).
- Imports ordenados alphabeticamente, trailing commas em multiline (Pint enforça).
- PHPStan level 6 com Larastan — código deve passar sem erros.

## Invariantes de Domínio (preservar sempre)

- **Nunca `PUT`** — apenas GET/POST/PATCH/DELETE.
- List endpoints usam **cursor pagination**; `ordering` como input do cliente é rejeitado.
- **Soft-delete nas entidades de domínio** (budgets/expenses/etc.); reads padrão excluem trashed; um scope "all-records" existe para auditoria. **Exceção consciente:** `User` **não** tem soft-delete — desativação de conta é feature futura, não invariante atual.
- **Budget overlap** enforcado no **DB** (Postgres range-exclusion), concurrency-safe.
- **Expense↔budget é dinâmico por data** — sem FK armazenada; "active budget" = o que cobre hoje.
- **Unified error envelope** `{errors:[{field,message,code}]}`.
- Categorização e chat backends são **estratégias plugáveis atrás de contracts**.

## Ambiente Local

Dev roda em **Laravel Sail** (Docker): `laravel.test` + Postgres 17 + Valkey 8.

- `sail up -d` → sobe app + DB + cache
- `sail artisan horizon` → queue worker
- `sail composer test` → Pest
- `sail composer lint` / `lint:test` → Pint
- `sail composer analyse` → Larastan/PHPStan
