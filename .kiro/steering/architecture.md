---
inclusion: auto
---

# Trocado — Arquitetura e Convenções

## Projeto

**Trocado** é uma JSON API para finanças pessoais de casais. Cada usuário possui seus próprios budgets/expenses; dois usuários podem se parear num "couple" para uma visão compartilhada, somente leitura.

O domínio é definido numa spec (Spec-Driven Development) originada numa implementação Django/DRF. Estamos **reimplementando esse domínio em Laravel 13** — replicando o _comportamento/contrato_, não o stack Django. A spec é a fonte de verdade; quando um comportamento for ambíguo, **pergunte ao invés de inventar**.

## Arquitetura: Vertical Slice

Código organizado por feature em `app/Features/<Feature>/`, não no layout flat padrão do Laravel. Cada feature tem três camadas:

```
app/Features/<Feature>/
├── Domain/
│   ├── Models/          # Eloquent models
│   ├── Services/        # Escrita + regras de negócio
│   └── Contracts/       # Interfaces de repositório
├── Infrastructure/
│   ├── Repositories/    # Implementações Eloquent dos contracts
│   └── Providers/       # Service provider da feature
└── Http/
    ├── Controllers/     # Slim (injeta Service, retorna Response)
    ├── Requests/        # FormRequest
    ├── Responses/       # JsonResource (sufixo Response)
    └── Routes/Routes.php
```

**Fluxo de requisição:** Route → Controller → Service → Repository → Model

## Wiring de Features

Cada feature tem um provider (registrado em `bootstrap/providers.php`) que:

1. Declara bindings `interface→impl` via `public array $bindings = [...]` — **sem `register()` manual**.
2. No `boot()` registra rotas via `Route::middleware('api')->prefix('api')->group(...)`.

Rotas são **descentralizadas**: cada feature possui seu `Http/Routes/Routes.php`.

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
- **Soft-delete em tudo**; reads padrão excluem trashed; um scope "all-records" existe para auditoria.
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
