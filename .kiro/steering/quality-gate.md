---
inclusion: auto
---

# Quality Gate

Antes de considerar qualquer tarefa concluída, rode o quality gate:

1. **Lint** — `composer lint:test` (Pint, sem escrita)
2. **Análise estática** — `composer analyse` (Larastan/PHPStan level 6)
3. **Testes** — `composer test` (Pest)

Se qualquer etapa falhar, corrija antes de apresentar o resultado.

Regras do Pint (#[[pint.json]]):
- Preset: `laravel`
- Imports ordenados alphabeticamente
- Sem imports não-usados
- Trailing comma em arrays, argumentos, e parâmetros multiline

Regras do PHPStan (#[[phpstan.neon]]):
- Level 6 com Larastan
- Paths: `app`, `database`, `routes`
