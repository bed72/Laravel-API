# Trocado — API

API JSON de **finanças pessoais para casais** (em construção). Cada usuário é dono do
próprio orçamento e despesas; dois usuários podem se *parear* num casal e ganhar uma
visão compartilhada somente-leitura, sem co-propriedade de dado nenhum.

> O domínio completo (regras, endpoints, invariantes) vive numa **spec stack-agnóstica**
> separada — ela é a fonte da verdade. Este README cobre como o projeto está organizado e
> como trabalhar nele.

### Estado atual

Esqueleto + a feature `Expenses` como referência viva da arquitetura. Hoje só o
`POST /api/expenses` é funcional (as rotas `index`/`show` ainda são stubs). Auth ainda
não está conectada — o `user_id` está fixo em `1` no controller, de propósito, até o JWT entrar.

---

## Stack

- **PHP 8.3+**, **Laravel 13**
- **Laravel Sail** (Docker) em dev: app + **Postgres 17** + **Valkey 8** (fork RESP-compatível do
  Redis). Postgres é necessário para a constraint de *overlap* de orçamento (`EXCLUDE USING gist`).
- **Horizon** sobre Valkey gerencia as filas (driver `redis`).
- **Pest** (testes), **Pint** (formatação), **Larastan** (análise estática)
- `laravel/sanctum` (auth — em transição para JWT), `laravel/pulse` (observabilidade)
- Os testes rodam em **SQLite `:memory:`** (rápido; ver seção Testes).

---

## Setup

Precisa de **PHP/Composer + Docker** no host. Um comando faz o bootstrap inteiro:

```bash
composer setup     # install + .env + sobe containers + migra/seeda + Horizon + assets
composer dev       # Horizon + logs + Vite (o app já é servido pelo Sail)
```

O que o `composer setup` encapsula, em ordem (via Sail):

1. `composer install` — instala as deps (cria o `vendor/bin/sail`)
2. copia `.env.example` → `.env`
3. `sail up -d` — sobe app + Postgres + Valkey
4. `sail artisan key:generate`
5. `sail artisan migrate --seed` — schema no Postgres + dados de seed
6. `sail composer require laravel/horizon` + `sail artisan horizon:install`
7. `sail npm install` + `sail npm run build`

> `sail` é só `docker compose` por baixo — sobe exatamente os serviços do `docker-compose.yml`.
> Depois do primeiro `up`, use `sail <comando>` (ex.: `sail artisan ...`, `sail composer ...`).
> Se o `migrate` falhar na 1ª vez (Postgres ainda subindo), rode `composer setup` de novo.

---

## Arquitetura

**Vertical slice / feature-based.** Em vez do layout plano do Laravel, o código é
organizado por feature em `app/Features/<Feature>/`, cada uma em três camadas:

```
app/Features/<Feature>/
  Http/             Controllers (finos), Requests, Responses (JsonResource), Routes
  Domain/           Models, Services (regras + escrita), Contracts (interfaces)
  Infrastructure/   Repositories (Eloquent), Providers (ServiceProvider da feature)
```

> O `User` mora em `app/Features/Users/Domain/Models/User.php` — **não** em `app/Models/`.

### Fluxo de uma requisição

`Rota → Controller → Service → Repository → Model`

- **Controller** é fino: injeta um Service e devolve um `Resource`.
- **Service** orquestra regra de negócio, escrita e efeitos colaterais.
- **Repository** isola o acesso a dados atrás de um contrato (`...RepositoryInterface`) —
  decisão deliberada por desacoplamento e controle explícito sobre o Eloquent.

### Cada feature se registra sozinha

Toda feature tem o próprio `ServiceProvider` (registrado em `bootstrap/providers.php`),
que estende um `FeatureServiceProvider` base e faz duas coisas:

```php
class ExpenseServiceProvider extends FeatureServiceProvider
{
    // Bind de contrato → implementação (lido nativamente pelo Laravel).
    public array $bindings = [
        ExpenseRepositoryInterface::class => ExpenseRepository::class,
    ];

    public function boot(): void
    {
        // api middleware + prefixo /api, com caminho relativo (à prova de refactor).
        $this->loadFeatureRoutes(__DIR__.'/../../Http/Routes/Routes.php');
    }
}
```

As rotas são **descentralizadas**: cada feature declara as suas em `Http/Routes/Routes.php`.
O `routes/api.php` raiz guarda só o essencial. O `FeatureServiceProvider::loadFeatureRoutes()`
aceita `middleware`/`prefix` como parâmetro (default `api`/`api`), para que o admin e as
páginas web de conta possam usar outra camada sem furar o padrão.

### Convenções

- Models declaram campos via **atributos** (Laravel 13), não propriedades:
  `#[Fillable([...])]` e `#[Hidden([...])]`. Casts ficam no método `casts()`.
- Erros de API saem como JSON para qualquer rota `api/*` (config em `bootstrap/app.php`).
- **Fiação de factory:** como os models saíram de `app/Models`, a convenção padrão do
  Laravel não resolve a factory sozinha. Cada model aponta a sua explicitamente:

  ```php
  protected static function newFactory(): ExpenseFactory
  {
      return ExpenseFactory::new();
  }
  ```

### Adicionar uma feature

1. Crie `app/Features/<Nome>/{Http,Domain,Infrastructure}/...`.
2. Defina a interface do repositório em `Domain/Contracts` e a impl em `Infrastructure/Repositories`.
3. Crie um `<Nome>ServiceProvider` (estendendo `FeatureServiceProvider`) com o mapa
   `$bindings` e o `loadFeatureRoutes(...)`, e registre-o em `bootstrap/providers.php`.

---

## Banco de dados

- **Migrations** em `database/migrations/`.
- **Factories** em `database/factories/` (cada uma com `$model` explícito).
- **Seeders** por feature, orquestrados pelo `DatabaseSeeder` na ordem das FKs:

```bash
sail artisan db:seed                          # roda o DatabaseSeeder (User → Expense)
sail artisan db:seed --class=ExpenseSeeder    # uma feature só
sail artisan migrate:fresh --seed             # recria o schema e popula do zero
```

O `UserSeeder` cria um usuário fixo `test@example.com` para login manual em dev.

---

## Testes (Pest)

```bash
composer test                       # suíte completa (php artisan test detecta o Pest)
./vendor/bin/pest                    # idem, direto
./vendor/bin/pest --filter Expense   # um arquivo/grupo
```

- **`tests/Feature/`** — testes HTTP. Sobem o Laravel e usam `RefreshDatabase` (ligados
  via `tests/Pest.php`). Testam comportamento ponta-a-ponta — são a espinha dorsal.
- **`tests/Unit/`** — testes puros, **sem** boot do Laravel (rápidos). É onde o
  desacoplamento se paga: a Service é testada trocando o repositório por um mock (Mockery).
- Casos repetitivos (validação, fórmulas) usam **datasets** (`->with([...])`) em vez de
  métodos duplicados.
- Banco de teste: **SQLite em memória** (`phpunit.xml`) — rápido e isolado. Seeders **não** rodam
  nos testes; cada teste monta o cenário mínimo com factory. Exceção: a constraint de *overlap* de
  orçamento (§3.4) só existe em Postgres, então *aquele* teste vai precisar rodar contra o `pgsql` do Sail.

**Princípio:** testa-se *lógica*, não *camada porque existe*. Um repositório que só repassa
pro Eloquent não ganha teste (seria testar o Laravel); ele passa a merecer um teste de
integração no dia que carregar uma regra (query na mão, scope de soft-delete, agregação).

### Teste de carga (opcional)

`pest-plugin-stressless` simula N usuários simultâneos contra um endpoint de pé (k6 por baixo):

```bash
composer require pestphp/pest-plugin-stressless --dev
./vendor/bin/pest stress http://127.0.0.1:8000/budgets/active --concurrency=20 --duration=10
```

Uso recomendado: **guardião de regressão de performance** (pegar N+1 num selector), não
termômetro de capacidade absoluta.

---

## Qualidade de código

Duas ferramentas que se complementam — rode as duas:

| Comando | Ferramenta | Faz |
|---|---|---|
| `composer lint` | Pint | formata e reescreve (estilo) |
| `composer lint:test` | Pint | só checa, não escreve (CI / pre-commit) |
| `composer analyse` | Larastan/PHPStan | acha bug, tipo errado, método inexistente |

```bash
composer require --dev larastan/larastan    # instala o analisador (Pint já vem)
```

- **`pint.json`** — preset `laravel` com regras explícitas (imports ordenados, sem import
  morto, vírgula final em multiline).
- **`phpstan.neon`** — nível **6** sobre `app`/`database`/`routes`. Suba até `max` conforme
  for limpando. `tests/` fica de fora por ora (closures do Pest geram falso-positivo).

> Pint nunca pega bug — deixa bonito código errado. Quem morde é o PHPStan.

---

## Comandos úteis

| Comando | O que faz |
|---|---|
| `composer dev` | sobe os containers + Horizon + Pail (logs) + Vite num comando só |
| `sail up -d` / `sail down` | sobe / derruba app + Postgres + Valkey |
| `sail artisan horizon` | worker de fila + dashboard `/horizon` |
| `sail composer test` | roda a suíte de testes |
| `sail composer lint` / `sail composer analyse` | formata / analisa |
| `sail artisan migrate:fresh --seed` | recria e popula o banco |
| `sail artisan route:list` | lista as rotas (inclusive as das features) |
