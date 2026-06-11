# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

This is the **Trocado** backend — a JSON API for couples' personal finance (each user owns their own
budgets/expenses; two users can pair into a couple for a derived, read-only shared view).

The **domain** (entities, endpoints, invariants) is defined in a Spec-Driven-Development catalog that
originated in a **Django/DRF** implementation, documented in Obsidian (`Trocado/BackEnd/*`) and managed
with **OpenSpec** (GIVEN/WHEN/THEN requirements). We are **re-implementing that domain in Laravel** —
replicate the *behavior/contract*, not the Django stack. The spec is the source of truth; when a behavior
is unclear, **ask rather than invent**.

## Local environment & commands

Dev runs on **Laravel Sail** (Docker): `docker-compose.yml` defines `laravel.test` + **Postgres 17** +
**Valkey 8** (a RESP-compatible Redis fork). Sail is just a `docker compose` wrapper — it brings up exactly
those services. Containers talk by service name, so `.env` uses `DB_HOST=pgsql`, `REDIS_HOST=valkey`. Prefix
commands with `sail` (e.g. `sail artisan ...`, `sail composer ...`).

- `sail up -d` — start app + Postgres + Valkey. `sail artisan horizon` — queue worker + `/horizon` dashboard.
- `sail composer test` — Pest suite. One test: `sail artisan test --filter <name>`.
- `sail composer lint` / `lint:test` — Pint. `sail composer analyse` — Larastan/PHPStan (level 6).
- `sail artisan migrate:fresh --seed` — rebuild + seed the DB (Postgres).

Slash commands (`.claude/commands/`):
- `/new-feature <Name>` — scaffold a feature slice per the conventions below.
- `/check` — run the quality gate (lint + analyse + test).
- `/cover <Feature>` — write/extend Pest tests for a feature.
- `/opsx:explore`, `/opsx:propose`, `/opsx:apply`, `/opsx:archive` — the OpenSpec SDD workflow (see below).

## Architecture

**Vertical slice / feature-based** — code is organized by feature under `app/Features/<Feature>/`, not
Laravel's default flat layout. Each feature has three layers:

- `Domain/` — `Models/`, `Services/` (writes + business rules), `Contracts/` (repository interfaces).
  The `User` model lives in `app/Features/Users/Domain/Models/User.php`, **not** `app/Models/`.
- `Infrastructure/` — `Repositories/` (Eloquent impls of the contracts), `Providers/` (the feature provider).
- `Http/` — `Controllers/` (slim), `Requests/` (FormRequest), `Responses/` (JsonResource, suffixed `Response`), `Routes/Routes.php`.

Request flow: **Route → Controller → Service → Repository → Model**. Controllers inject a Service and
return `...Response::make(...)`. The repository is a **deliberate** decoupling layer (today a thin
passthrough; a `Selectors/` read-layer will join it for complex queries — keep heavy aggregations out of
generic repositories, where they invite N+1).

### How a feature wires itself

Each feature has a provider (registered in `bootstrap/providers.php`) that **extends**
`App\Support\Providers\FeatureServiceProvider` and:

1. Declares interface→impl via the native `public array $bindings = [...]` property — **no manual `register()`**.
2. In `boot()` calls `$this->loadFeatureRoutes(__DIR__.'/../../Http/Routes/Routes.php')`. The base applies
   `api` middleware + `/api` prefix by default; pass overrides for admin / web-account features.

Routes are **decentralized**: each feature owns its `Http/Routes/Routes.php`. `Expenses` is the canonical
template — mirror it (and prefer `/new-feature`).

### Conventions

- Models use **PHP attributes** (Laravel 13): `#[Fillable([...])]` / `#[Hidden([...])]`, not `$fillable`/`$hidden`. Casts in `casts()`.
- Models live outside `app/Models`, so each **must** declare its factory explicitly:
  `protected static function newFactory(): XFactory { return XFactory::new(); }` (and the factory sets `protected $model`). Without this, factory resolution silently breaks.
- Ownership uses `$request->user()->id` — **never** hardcode a user id.
- API errors render as JSON for `api/*` (`bootstrap/app.php`).

## Testing (Pest)

- `tests/Feature/` — HTTP tests; boot Laravel + `RefreshDatabase` (wired in `tests/Pest.php`). The backbone.
- `tests/Unit/` — pure, no Laravel boot; services tested with the repository mocked (Mockery).
- Repetitive cases use **datasets** (`->with([...])`). Test DB is in-memory SQLite; seeders don't run in tests.
  (Exception: the Postgres-only budget-overlap `EXCLUDE` constraint can't run on SQLite — that one test needs the `pgsql` connection.)
- **Principle:** test logic, not a layer because it exists. A passthrough repository gets no test; it earns
  one when it carries a rule (hand query, soft-delete scope, aggregation).
- Load/regression: `pest-plugin-stressless` (`./vendor/bin/pest stress <url> --concurrency=N`) — use it to
  catch perf regressions (N+1 in a selector), not as an absolute-capacity gauge.

## Domain invariants (from the spec — preserve these)

- **Never `PUT`** — only GET/POST/PATCH/DELETE. List endpoints use **cursor pagination**; `ordering` as client input is rejected.
- **Soft-delete everywhere**; default reads exclude trashed; an all-records scope exists for audit.
- **Budget overlap** is enforced at the **DB level** (Postgres range-exclusion), concurrency-safe, not app-only. `value > 0`; `end_date` strictly after `start_date`.
- **Expense↔budget is dynamic by date** — no stored FK; "active budget" = the one covering today.
- **JWT**: access ~15 min, refresh ~30 days with rotation + blacklist (Sanctum's default is not this — needs a JWT layer).
- **Unified error envelope** `{errors:[{field,message,code}]}`; `code` is the stable client/i18n contract.
- Categorization (`keyword|ollama|none`) and chat backends are **pluggable strategies behind contracts** — this is where interfaces pay off (not generic persistence).

## Spec-Driven Development (OpenSpec)

The project follows SDD: **write the spec before the code.** OpenSpec is installed via npm (`openspec` CLI;
fallback `npx -y @fission-ai/openspec@latest`). A permanent catalog lives in `openspec/specs/<capability>/spec.md`;
changes run through `openspec/changes/<name>/` (proposal → design → specs delta → tasks) and are archived on
completion, applying their deltas to the catalog.

Use the `/opsx:*` commands. Spec format: `### Requirement:` (SHALL/MUST), each with ≥1 `#### Scenario:`
(**exactly 4 hashtags**) in GIVEN/WHEN/THEN. Any behavior the mobile client can observe must go through a
change. Validate with `openspec validate <change> --strict` before archiving. A refactor or a bug fix that
changes no observed behavior does **not** need a change.

> Requires a one-time `openspec init` in this repo (not done yet) and the specs catalog ported from the Django source.

## Known debts (deliberate, documented)

- `ExpenseController::store` hardcodes `userId = 1` (no auth yet) and returns **200** (create should be **201**).
- `Expenses` routes `index`/`show` point to controller methods that don't exist yet (stubs).
- `Expense` has no `SoftDeletes` trait or `date` column yet (the spec wants both).
- **Queue on Valkey/Horizon revises spec §15:** Redis can't enlist in the Postgres transaction, so the spec's
  "enqueue inside the transaction" hard rule becomes **after-commit dispatch + idempotent jobs**. Capture this
  as an OpenSpec change. (`laravel/reverb` was removed as unused.)
