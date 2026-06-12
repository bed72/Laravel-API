---
inclusion: fileMatch
fileMatchPattern: "app/Features/**"
---

# Trocado — Scaffold de Feature (Vertical Slice)

Ao criar ou modificar uma feature em `app/Features/<Feature>/`, siga esta estrutura canônica.
O template de referência é `app/Features/Expenses/`.

## Estrutura Obrigatória

```
app/Features/<Feature>/
├── Domain/
│   ├── Models/<Feature>.php
│   ├── Services/<Feature>Service.php
│   └── Contracts/<Feature>RepositoryInterface.php
├── Infrastructure/
│   ├── Repositories/<Feature>Repository.php
│   └── Providers/<Feature>ServiceProvider.php
└── Http/
    ├── Controllers/<Feature>Controller.php
    ├── Requests/Store<Feature>Request.php
    ├── Responses/<Feature>Response.php
    └── Routes/Routes.php
```

Além disso:
- `database/migrations/..._create_<plural>_table.php`
- `database/factories/<Feature>Factory.php`
- `tests/Feature/<Feature>/Create<Feature>Test.php`
- `tests/Unit/<Feature>/<Feature>ServiceTest.php`

## Padrão do Model

```php
<?php

namespace App\Features\<Feature>\Domain\Models;

use Database\Factories\<Feature>Factory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([/* campos */])]
class <Feature> extends Model
{
    /** @use HasFactory<<Feature>Factory> */
    use HasFactory;

    protected static function newFactory(): <Feature>Factory
    {
        return <Feature>Factory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [/* casts */];
    }
}
```

## Padrão do ServiceProvider

```php
<?php

namespace App\Features\<Feature>\Infrastructure\Providers;

use App\Features\<Feature>\Domain\Contracts\<Feature>RepositoryInterface;
use App\Features\<Feature>\Infrastructure\Repositories\<Feature>Repository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class <Feature>ServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        <Feature>RepositoryInterface::class => <Feature>Repository::class,
    ];

    public function boot(): void
    {
        $this->app->booted(function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(__DIR__.'/../../Http/Routes/Routes.php');
        });
    }
}
```

Registre o provider em `bootstrap/providers.php`.

## Padrão do Controller (slim)

```php
<?php

namespace App\Features\<Feature>\Http\Controllers;

use App\Features\<Feature>\Domain\Services\<Feature>Service;
use App\Features\<Feature>\Http\Requests\Store<Feature>Request;
use App\Features\<Feature>\Http\Responses\<Feature>Response;

class <Feature>Controller
{
    public function __construct(
        private readonly <Feature>Service $service,
    ) {}

    public function store(Store<Feature>Request $request): <Feature>Response
    {
        $resource = $this->service->create(
            $request->user()->id,
            $request->validated(),
        );

        return <Feature>Response::make($resource);
    }
}
```

## Regras

- Ownership via `$request->user()->id` — **nunca** hardcodar user id.
- Controllers são slim: injetam Service, retornam Response.
- Service injeta a interface do repositório (não a implementação).
- Repositório é passthrough fino; lógica complexa vive na Service.
- **Nunca `PUT`** — apenas GET/POST/PATCH/DELETE.
- Não invente colunas ou validation rules sem confirmar com a spec.
