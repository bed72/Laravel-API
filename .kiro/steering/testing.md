---
inclusion: fileMatch
fileMatchPattern: "tests/**"
---

# Trocado — Convenções de Testes (Pest)

## Estrutura

- `tests/Feature/` — Testes HTTP; bootam Laravel + `RefreshDatabase` (wired em `tests/Pest.php`).
- `tests/Unit/` — Services testados com repositório mockado (Mockery). Bootam o container (`TestCase`) apenas quando necessitam mockar facades (`Hash`, etc.), mas **sem DB** (sem `RefreshDatabase`).
- `tests/Stress/` — Testes de carga contra o servidor real (sem `RefreshDatabase`). Rodam separadamente via `composer test:stress`.

## Regras

1. Casos repetitivos usam **datasets** (`->with([...])`).
2. DB de teste é in-memory SQLite; seeders **não** rodam em testes.
3. **Princípio:** teste lógica, não uma camada só porque existe. Repositório passthrough não ganha teste; ganha quando carrega uma regra.
4. Teste unitário encerra com `afterEach(fn () => Mockery::close())`.
5. Feature tests: happy path (status + JSON shape + DB assertion) e validation failures.
6. Collapse same-field invalid cases num único `it(...)->with([...])` dataset.
7. **Cobertura mínima por feature:** happy path, falhas de validação, falhas de domínio (DomainError), autenticação/autorização, e edge cases relevantes (non-enumeration, idempotência).
8. **Middleware e comportamento transversal** (rate limiting, token rotation, error envelope) devem ter testes dedicados na feature que os define.
9. Use assertions descritivas do Pest: `assertCreated()`, `assertUnauthorized()`, `assertNoContent()` — não `assertStatus(201)`.

## Helper de Criação de Service (Unit Tests)

Para testes unitários de services com múltiplas dependências, crie uma `makeService()` helper no topo do arquivo:

```php
function makeService(
    ?RepositoryInterface $repository = null,
    ?NotifierInterface $notifier = null,
): MyService {
    return new MyService(
        repository: $repository ?? Mockery::mock(RepositoryInterface::class),
        notifier: $notifier ?? Mockery::mock(NotifierInterface::class),
    );
}
```

## Padrão de Feature Test

```php
<?php

use App\Features\Users\Domain\Models\User;

it('creates a <resource> with valid data', function () {
    User::factory()->create();

    $this->postJson('/api/<resources>', [/* valid payload */])
        ->assertCreated()
        ->assertJsonStructure([/* fields */])
        ->assertJsonPath('<field>', '<value>');

    $this->assertDatabaseHas('<table>', [/* expected row */]);
});

it('rejects invalid <field>', function (array $payload, string $field) {
    $this->postJson('/api/<resources>', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', $field);
})->with([
    'missing' => [['other' => 'value'], '<field>'],
    'invalid format' => [['<field>' => 'bad'], '<field>'],
]);
```

## Padrão de Unit Test

```php
<?php

use App\Features\<Feature>\Domain\Repositories\<Feature>RepositoryInterface;
use App\Features\<Feature>\Application\Services\<Feature>Service;

uses(Tests\TestCase::class);

afterEach(fn () => Mockery::close());

it('<descreve o comportamento da service>', function () {
    $repository = Mockery::mock(<Feature>RepositoryInterface::class);
    $repository->shouldReceive('<method>')
        ->once()
        ->with([/* expected args */])
        ->andReturn($expected);

    $result = makeService(repository: $repository)-><method>(/* args */);

    expect($result)->toBe($expected);
});
```

## Comandos

- Rodar unit + feature: `composer test` (exclui stress)
- Rodar stress: `composer test:stress` (contra servidor Sail rodando)
- Um teste: `sail artisan test --filter <name>`
- Load test CLI: `./vendor/bin/pest stress <url> --concurrency=N`
