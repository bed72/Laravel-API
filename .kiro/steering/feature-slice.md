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
│   ├── Repositories/<Feature>RepositoryInterface.php   # port de repositório
│   └── Gateways/                                       # ports de gateway (só se a feature falar com mecanismo externo)
├── Application/
│   ├── Services/<Feature>Service.php
│   └── Data/                               # DTOs (só se a feature trafegar dados entre camadas)
├── Infrastructure/
│   └── Repositories/<Feature>Repository.php
├── Presentation/
│   ├── Controllers/<Feature>Controller.php
│   ├── Requests/Store<Feature>Request.php
│   └── Responses/<Feature>Response.php
└── Main/                                   # composition root da feature
    ├── Providers/<Feature>ServiceProvider.php
    └── Routes/Routes.php
```

Além disso:
- `database/migrations/..._create_<plural>_table.php`
- `database/factories/<Feature>Factory.php`
- `tests/Feature/<Feature>/Create<Feature>Test.php`
- `tests/Unit/<Feature>/<Feature>ServiceTest.php`

> **Regra dos +2 parâmetros:** método com mais de 2 params de dados empacota num DTO de entrada em `Application/Data/` (sufixo `Data`). Não conta construtor (DI) nem params que são entidades. Ver `architecture.md`.

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

namespace App\Features\<Feature>\Main\Providers;

use App\Features\<Feature>\Domain\Repositories\<Feature>RepositoryInterface;
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
                ->group(__DIR__.'/../Routes/Routes.php');
        });
    }
}
```

Registre o provider em `bootstrap/providers.php`.

## Padrão do Controller (slim)

```php
<?php

namespace App\Features\<Feature>\Presentation\Controllers;

use App\Features\<Feature>\Application\Services\<Feature>Service;
use App\Features\<Feature>\Presentation\Requests\Store<Feature>Request;
use App\Features\<Feature>\Presentation\Responses\<Feature>Response;

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
