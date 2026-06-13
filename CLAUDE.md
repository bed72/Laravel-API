# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**Trocado** — a JSON API for couples' personal finance (Laravel 13, PHP 8.3+). Each user owns
their own budgets/expenses; two users can pair into a "couple" for a shared **read-only** view
(no co-ownership of data). The domain is being **reimplemented in Laravel from a stack-agnostic
spec** originally shaped by a Django/DRF implementation — replicate the *behavior/contract*, not
the Django stack.

**The spec (OpenSpec catalog) is the source of truth.** When a behavior is ambiguous, **ask
instead of inventing** — do not make up columns or validation rules.

Current state: skeleton + `Expenses` as the living reference feature, plus `Authentication`.
Only `POST /api/expenses` is fully wired; `index`/`show` are stubs.

## Authoritative docs — read these

The steering docs in `.kiro/steering/` are the canonical conventions and override anything
inferred from code. Read the relevant one before working in an area:

- `.kiro/steering/architecture.md` — vertical-slice layout, feature wiring, code conventions
- `.kiro/steering/feature-slice.md` — canonical scaffold + code templates for a new feature
- `.kiro/steering/domain-invariants.md` — inviolable domain rules (read before touching `Domain/`)
- `.kiro/steering/testing.md` — Pest conventions, feature/unit/stress split, test templates
- `.kiro/steering/quality-gate.md` — the lint/analyse/test gate that must pass before "done"

`README.md` covers setup and the same architecture in prose (Portuguese).

## Commands

Dev runs in **Laravel Sail** (Docker): `laravel.test` + Postgres 17 + Valkey 8 (Redis-compatible).
Prefix everything with `sail` once containers are up (`sail artisan ...`, `sail composer ...`).

```bash
composer setup        # one-shot bootstrap: install + .env + sail up + migrate --seed + horizon + assets
composer dev          # Horizon + pail logs + Vite (app is served by Sail)

composer test         # full Pest suite, excludes Stress (php artisan test detects Pest)
composer test:stress  # load tests against a running Sail server
sail artisan test --filter <name>          # run a single test
./vendor/bin/pest --filter Expense         # run one file/group directly

composer lint         # Pint — formats/rewrites
composer lint:test    # Pint — check only (CI / pre-commit)
composer analyse      # Larastan/PHPStan level 6 over app/database/routes
```

**Quality gate before declaring a task done:** `composer lint:test` → `composer analyse` → `composer test`.
Pint only styles; PHPStan catches the bugs.

### Database

```bash
sail artisan db:seed                       # DatabaseSeeder, ordered by FK (User → Expense)
sail artisan db:seed --class=ExpenseSeeder # one feature
sail artisan migrate:fresh --seed          # recreate schema + reseed
```
`UserSeeder` creates a fixed `test@example.com` for manual dev login.

## Architecture (the big picture)

**Vertical slice / feature-based** — NOT Laravel's flat layout. Code lives in
`app/Features/<Feature>/` in five module-root layers, request flow is `Route → Controller → Service → Repository/Gateway → Model`:

```
app/Features/<Feature>/
  Domain/         Models, ValueObjects (immutable + self-validating), Repositories + Gateways (domain ports — `*Interface`, split by kind; impls live in Infrastructure)
  Application/    Services (use-case orchestration) + Data/ (DTOs — use-case I/O, no invariants, `Data` suffix, e.g. IssuedTokenData, AuthenticationSessionData)
  Infrastructure/ Repositories (Eloquent impl), Gateways (adapters over external mechanisms), Notifications, Jobs
  Presentation/   (formerly Http) Controllers (slim), Requests, Responses (JsonResource, `*Response` suffix), Middleware
  Main/           Feature composition root: Providers/<Feature>ServiceProvider (DI bindings) + Routes/Routes.php (loaded by the provider)
```

Dependency rule: inner layers (`Domain`, `Application`) never import Infrastructure concretions or
Sanctum — they depend on the domain ports (`Domain/Repositories`, `Domain/Gateways`) only. The `ServiceProvider`
lives in `Main/` (the feature's composition root — the one layer allowed to know all the others, where
concretions are wired); an arch test guards Domain + Application against Infrastructure/Sanctum.

- The `User` model lives in `app/Features/Users/Domain/Models/User.php`, **not** `app/Models/`.
- `app/Core/` holds cross-cutting code, mirroring the feature layout (e.g. `Core/Main/Providers/HorizonServiceProvider`, `Core/Domain/...`).

**Each feature self-registers.** Its `ServiceProvider` (listed in `bootstrap/providers.php`)
declares `public array $bindings = [Interface::class => Impl::class]` (no manual `register()`)
and loads its own routes in `boot()` under the `api` middleware + `/api` prefix. Routes are
**decentralized** — each feature owns `Main/Routes/Routes.php`; root `routes/api.php` stays minimal.

**To add a feature:** scaffold the module-root layers, define the repo interface in `Domain/Repositories`
+ impl in `Infrastructure/Repositories`, create the `ServiceProvider`, register it in
`bootstrap/providers.php`. Follow `.kiro/steering/feature-slice.md` templates exactly.

### Service / Repository / Gateway classification (decided, keep consistent)

These distinctions were made deliberately — don't re-blur them:

- **Service** (`Application/Services`) is an **application service**: it *orchestrates* a use case
  (queries repos, calls gateways/notifiers, maps failure to `DomainError`). It may hold thin
  business rules, but its job is coordination, not computation. It's callable from a controller,
  job, or CLI unchanged. Lives in `Application/` (not `Domain/`) — a deliberate project-wide layer.
  Its DTOs (use-case I/O like `AuthenticationSessionData`) live alongside it in `Application/Data/`.
  `Domain/` is left with pure domain types + ports only: Models, ValueObjects, Repositories, Gateways (interfaces).
- **Repository vs Gateway** — the test is **not** "does it touch data" (almost everything does):
  - **Repository** = collection of *your own aggregates*; returns *your entities* (`User`,
    `Expense`). Swapping the impl = swapping DB/ORM. `UserRepository` is the only real one in
    `Authentication`.
  - **Gateway** = adapter over an *external mechanism* (Sanctum, `Password::broker()`); returns a
    credential/outcome, not your entity. Swapping the impl = swapping the *provider*.
    `SanctumTokenIssuer` and `PasswordResetBroker` are gateways — they live in
    `Infrastructure/Gateways/`, **not** `Repositories/` and **not** a generic `Services/`.
  - Pocket test: *"would I call `->find()`/`->paginate()` and get an entity of mine back?"*
    Yes → repository; an outcome/credential → gateway.
- **Don't create folders per pattern stereotype** (`Issuer/`, `Broker/`, `Notifier/`): the class
  suffix already encodes the pattern; a folder earns its place by holding several cohesive things,
  not by naming a pattern.
- **Class-naming families.** Stereotype/role classes carry a suffix matching their stereotype
  (`Service`, `Repository`, `Job`, `Notification`, `Request`, `Response`, `Data` for DTOs); pure
  domain concepts do **not** (`User`, `Expense`, future VOs like `Money`). A DTO is a transport
  stereotype, so it follows the suffix family → `AuthenticationSessionData`, `IssuedTokenData`.
- **Input DTO over loose params (>2 rule).** A method that takes **more than 2 parameters of plain
  data** (a data clump describing one thing) MUST bundle them into an input DTO in `Application/Data/`
  (`Data` suffix — e.g. `CreateUserData`, `ResetPasswordData`, `CreateExpenseData`) instead of
  positional scalars; this kills same-typed argument mix-ups. The count **excludes** constructor DI
  params and parameters that are entities / distinct domain objects (e.g. `reset(User $user, string
  $token, string $newPassword)` is 2 data params → stays; `rotate(IssuedTokenData, User, int)` is
  distinct objects, not a clump → stays). ≤2 data params: positional with named args is fine.
- **Domain stays free of token infrastructure.** The token lifecycle sits behind
  `TokenIssuerInterface` (Sanctum only in the gateway impl); `AuthenticationService` never imports
  `Laravel\Sanctum\*`. An arch test enforces this. **Boundary rule:** abstract *stateful* resources
  (token store, `Password::broker()`) behind contracts; *stateless* utilities (`Hash`) may be used
  directly in the Service. Token policy (TTL 30d, rotation 7d) lives in the Service, not the gateway.

### Non-obvious conventions

- **Models use PHP attributes** (Laravel 13): `#[Fillable([...])]` / `#[Hidden([...])]`, not
  `$fillable`/`$hidden`. Casts go in a `casts()` method.
- Because models live outside `app/Models`, each **must** declare its factory explicitly:
  `protected static function newFactory(): XFactory { return XFactory::new(); }` — otherwise
  Laravel's convention can't resolve it.
- Ownership is always `$request->user()->id` — **never** hardcode a user id.
- API errors render as JSON for any `api/*` route (configured in `bootstrap/app.php`), using a
  unified envelope `{errors:[{field, message, code}]}` — `code` is the stable contract for client i18n.

### Domain invariants (preserve always — see domain-invariants.md)

- **Never `PUT`** — only GET/POST/PATCH/DELETE.
- List endpoints use **cursor pagination**; client-supplied `ordering` is rejected.
- **Soft-delete on domain entities** (budgets/expenses/…); default reads exclude trashed; an
  "all-records" scope exists for audit. **Conscious exception: `User` has no soft-delete** (no trait,
  no `deleted_at`) — account deactivation is a future feature, not a current invariant. Don't add
  branches assuming trashed users.
- **Budget overlap** is enforced in the DB (Postgres `EXCLUDE USING gist` range-exclusion) — this is
  why Postgres is required in dev even though tests use SQLite.
- **Expense↔budget is dynamic by date** — no stored FK; the "active budget" is whatever covers today.
- Auth: **Sanctum opaque PAT is the chosen model — not a stopgap.** 30-day tokens, rotated after 7
  days via the `RotateToken` middleware → `X-New-Token` header. Tokens are server-side and revoked
  by deletion (no blacklist). **JWT is explicitly NOT a target** (the spec's access-15min/refresh-30d
  + blacklist model is overridden); don't build toward it. Accepted trade-off: a 30-day bearer token
  has a longer exposure window than a 15-min access token — rotation mitigates; shorten the TTL if
  needed. Keep the `TokenIssuerInterface` port regardless — its remaining justification is
  testability + keeping `Application` free of `Laravel\Sanctum\*`, not provider-swap.
- **No admin/2FA surface.** The spec's TOTP/sudo-mode admin (§16) was a Django-admin concern; this is
  a JSON API with no server-rendered admin, so it is **not a target**. Destructive ops live as
  user-facing use-cases (`DELETE /me`) or shell/CLI commands, not behind a 2FA gate.
- Queue: Valkey can't join the Postgres transaction — use **after-commit dispatch + idempotent jobs**.
- Categorization (`keyword | ollama | none`) and chat backends are **pluggable strategies behind contracts**.

## Testing

Tests run on **SQLite `:memory:`** (`phpunit.xml`) — seeders do **not** run; each test builds its
minimal scenario via factory. Exception: the budget-overlap constraint only exists in Postgres, so
that specific test must run against the Sail `pgsql` service.

- `tests/Feature/` — HTTP tests, boot Laravel + `RefreshDatabase` (wired in `tests/Pest.php`). The backbone.
- `tests/Unit/` — Services tested with the repository swapped for a Mockery mock; no DB.
- `tests/Stress/` — load tests vs a running server; run separately via `composer test:stress`.

**Principle:** test *logic*, not a layer because it exists. A passthrough repository gets no test
(that'd be testing Laravel); it earns one when it carries a real rule. Use descriptive Pest
assertions (`assertCreated()`, `assertUnauthorized()`) and collapse repetitive cases into datasets.
