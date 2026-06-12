---
inclusion: manual
---

# Skill: Scaffold de Nova Feature

Scaffold uma nova feature vertical-slice em `app/Features/<Feature>/`, espelhando `app/Features/Expenses/` como template canônico.

## Instruções

Ao receber o nome da feature (singular, PascalCase), leia a feature Expenses primeiro como referência e crie a estrutura paralela. Nome do model singular PascalCase; tabela/route prefix plural snake/kebab.

### Arquivos a Criar

1. **Model** `app/Features/<Feature>/Domain/Models/<Feature>.php`
   - Extends `Model`, `use HasFactory`, `#[Fillable([...])]`, método `casts()`
   - **Obrigatório:** `protected static function newFactory(): <Feature>Factory { return <Feature>Factory::new(); }`

2. **Contract** `app/Features/<Feature>/Domain/Contracts/<Feature>RepositoryInterface.php`

3. **Repository** `app/Features/<Feature>/Infrastructure/Repositories/<Feature>Repository.php`
   - Implementa o contract via Eloquent

4. **Service** `app/Features/<Feature>/Domain/Services/<Feature>Service.php`
   - Constructor-injects a interface; holds regras + side effects

5. **Controller** `app/Features/<Feature>/Http/Controllers/<Feature>Controller.php`
   - Slim; injeta Service; retorna `<Feature>Response`

6. **Request** `app/Features/<Feature>/Http/Requests/Store<Feature>Request.php`

7. **Response** `app/Features/<Feature>/Http/Responses/<Feature>Response.php`
   - JsonResource

8. **Routes** `app/Features/<Feature>/Http/Routes/Routes.php`
   - `Route::prefix('<plural>')->group(...)`

9. **Provider** `app/Features/<Feature>/Infrastructure/Providers/<Feature>ServiceProvider.php`
   - Declara `public array $bindings = [...]`
   - `boot()` registra rotas com middleware `api` e prefix `api`

10. **Registrar** provider em `bootstrap/providers.php`

11. **Migration** `database/migrations/..._create_<plural>_table.php`

12. **Factory** `database/factories/<Feature>Factory.php`
    - `protected $model = <Feature>::class`

13. **Tests:**
    - `tests/Feature/<Feature>/Create<Feature>Test.php` (HTTP happy path + validation datasets)
    - `tests/Unit/<Feature>/<Feature>ServiceTest.php` (service com repo mockado)

### Regras

- Ownership via `$request->user()->id` — **nunca** hardcode user id.
- Não invente colunas ou validation rules. Se a spec/domain for ambíguo, **pergunte**.
- Após scaffold, rode `composer lint` e `composer analyse` e corrija o que flagarem.
- Use PHP attributes (`#[Fillable]`, `#[Hidden]`) ao invés de properties `$fillable`/`$hidden`.
- Trailing commas em multiline (arrays, arguments, parameters).
