---
description: Scaffold a new vertical-slice feature following the project's conventions
argument-hint: <FeatureName> singular PascalCase, e.g. Budget
---

Scaffold a new feature named **$1** under `app/Features/$1/`, mirroring the existing
`app/Features/Expenses/` feature exactly. Read the Expenses feature first as the canonical
template, then create the parallel structure for `$1` (model name singular PascalCase, table/route
prefix plural snake/kebab).

Create:

1. **Model** `app/Features/$1/Domain/Models/$1.php` — extends `Model`, `use HasFactory`,
   `#[Fillable([...])]`, a `casts()` method, and **required**:
   `protected static function newFactory(): \Database\Factories\$1Factory { return \Database\Factories\$1Factory::new(); }`
   (models live outside `app/Models`, so factory resolution must be explicit).
2. **Contract** `app/Features/$1/Domain/Contracts/$1RepositoryInterface.php`.
3. **Repository** `app/Features/$1/Infrastructure/Repositories/$1Repository.php` implementing it via Eloquent.
4. **Service** `app/Features/$1/Domain/Services/$1Service.php` — constructor-injects the interface; holds rules + side effects.
5. **Controller** `app/Features/$1/Http/Controllers/$1Controller.php` — slim; injects the Service; returns a `$1Response`.
6. **Request** `app/Features/$1/Http/Requests/Store$1Request.php` (and Update… if needed).
7. **Response** `app/Features/$1/Http/Responses/$1Response.php` — a `JsonResource`.
8. **Routes** `app/Features/$1/Http/Routes/Routes.php` — `Route::prefix('<plural>')->group(...)`.
9. **Provider** `app/Features/$1/Infrastructure/Providers/$1ServiceProvider.php` — extends
   `App\Support\Providers\FeatureServiceProvider`, declares
   `public array $bindings = [$1RepositoryInterface::class => $1Repository::class];`, and `boot()` calls
   `$this->loadFeatureRoutes(__DIR__.'/../../Http/Routes/Routes.php')`.
10. **Register** the provider in `bootstrap/providers.php`.
11. **Migration** `database/migrations/..._create_<plural>_table.php`.
12. **Factory** `database/factories/$1Factory.php` with `protected $model = $1::class`.
13. **Tests (Pest)**: `tests/Feature/$1/Create$1Test.php` (HTTP happy path + validation, datasets for
    invalid-field cases) and `tests/Unit/$1/$1ServiceTest.php` (service rule, repository mocked).

Rules:
- Use `$request->user()->id` for ownership — **never** hardcode a user id.
- Do **not** invent columns or validation rules. If the spec/domain detail is unclear, **ask me** before writing.
- After scaffolding, run `composer lint` and `composer analyse` and fix what they flag.
