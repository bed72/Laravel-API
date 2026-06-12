---
inclusion: manual
---

# Skill: Quality Gate (Check)

Roda o quality gate completo do projeto e reporta pass/fail por etapa.

## Instruções

Rode em ordem:

1. **Lint** — `composer lint:test` (Pint, sem escrita)
2. **Análise estática** — `composer analyse` (Larastan/PHPStan level 6)
3. **Testes** — `composer test` (Pest suite)

### Comportamento

- Reporte um sumário conciso pass/fail por etapa.
- Se alguma etapa falhar, mostre o output relevante e proponha fixes.
- **Não corrija automaticamente** a menos que seja pedido explicitamente.
- Pare e reporte se alguma tool não estiver instalada (ex: `larastan/larastan`).

### Comandos com Sail

Se o ambiente local usa Sail, prefixe: `sail composer lint:test`, `sail composer analyse`, `sail composer test`.
