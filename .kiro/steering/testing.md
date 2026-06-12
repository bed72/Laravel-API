---
inclusion: fileMatch
fileMatchPattern: "tests/**"
---

# Trocado — Convenções de Testes (Pest)

## Estrutura

- `tests/Feature/` — Testes HTTP; bootam Laravel + `RefreshDatabase` (wired em `tests/Pest.php`).
- `tests/Unit/` — Puros, sem boot do Laravel; services testados com repositório mockado (Mockery).

## Regras

1. Casos repetitivos usam **datasets** (`->with([...])`).
2. DB de teste é in-memory SQLite; seeders **não** rodam em testes.
3. **Princípio:** teste lógica, não uma camada só porque existe. Repositório passthrough não ganha teste; ganha quando carrega uma regra.
4. Teste unitário encerra com `afterEach(fn () => Mockery::close())`.
5. Feature tests: happy path (status + JSON shape + DB assertion) e validation failures.
6. Collapse same-field invalid cases num único `it(...)->with([...])` dataset.

## Padrão de Feature Test

```php
<?php

use App\Features\Users\Domain\Models\User;

it('creates a <resource> with valid data', function () {
    User::factory()->create();

    $this->postJson('/api/<resources>', [/* valid payload */])
        ->assertCreated()
        ->assertJsonStructure(['data' => [/* fields */]])
        ->assertJsonPath('data.<field>', '<value>');

    $this->assertDatabaseHas('<table>', [/* expected row */]);
});

it('rejects invalid <field>', function (mixed $value) {
    $this->postJson('/api/<resources>', ['<field>' => $value])
        ->assertStatus(422)
        ->assertJsonValidationErrors('<field>');
})->with([
    'missing' => [null],
    'non-numeric' => ['abc'],
    // ...
]);
```

## Padrão de Unit Test

```php
<?php

use App\Features\<Feature>\Domain\Contracts\<Feature>RepositoryInterface;
use App\Features\<Feature>\Domain\Models\<Feature>;
use App\Features\<Feature>\Domain\Services\<Feature>Service;

afterEach(fn () => Mockery::close());

it('<descreve o comportamento da service>', function () {
    $persisted = new <Model>;

    $repository = Mockery::mock(<Feature>RepositoryInterface::class);
    $repository->shouldReceive('<method>')
        ->once()
        ->with([/* expected args */])
        ->andReturn($persisted);

    $result = (new <Feature>Service($repository))-><method>(/* args */);

    expect($result)->toBe($persisted);
});
```

## Comandos

- Rodar todos: `composer test` (ou `sail composer test`)
- Um teste: `sail artisan test --filter <name>`
- Load test: `./vendor/bin/pest stress <url> --concurrency=N`
