---
inclusion: manual
---

# Skill: Cobertura de Testes

Escreve/estende testes Pest para uma feature, seguindo as convenções do projeto.

## Instruções

Ao receber o nome da feature, leia o código real da feature primeiro — não assuma comportamento.

### Feature Tests (`tests/Feature/<Feature>/`)

Comportamento HTTP de cada endpoint funcional:
- **Happy path:** status code correto, JSON shape, DB assertion
- **Validation failures:** colapsar casos de mesmo campo num único `it(...)->with([...])` dataset

Padrão:
```php
<?php

use App\Features\Users\Domain\Models\User;

it('creates a <resource> with valid data', function () {
    User::factory()->create();
    $this->postJson('/api/<resources>', [/* payload */])
        ->assertCreated()
        ->assertJsonStructure(['data' => [/* fields */]]);
    $this->assertDatabaseHas('<table>', [/* row */]);
});

it('rejects invalid <field>', function (mixed $value) {
    $this->postJson('/api/<resources>', ['<field>' => $value])
        ->assertStatus(422)
        ->assertJsonValidationErrors('<field>');
})->with([/* dataset */]);
```

### Unit Tests (`tests/Unit/<Feature>/`)

Regras de service com repositório mockado (Mockery); puros, sem boot Laravel.

Padrão:
```php
<?php

afterEach(fn () => Mockery::close());

it('<descreve regra>', function () {
    $repository = Mockery::mock(<Interface>::class);
    $repository->shouldReceive('<method>')->once()->with([...])->andReturn($expected);
    $result = (new <Service>($repository))-><method>(...);
    expect($result)->toBe($expected);
});
```

### Honestidade

Se uma rota aponta para um controller method inexistente, ou um status contradiz a spec (ex: 200 onde create deveria ser 201), **anote o problema** ao invés de assertar a coisa errada como correta.

### Finalização

Rode os novos testes ao final: `composer test`
