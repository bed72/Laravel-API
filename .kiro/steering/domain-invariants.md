---
inclusion: fileMatch
fileMatchPattern: "app/Features/*/Domain/**"
---

# Trocado — Invariantes de Domínio

Ao trabalhar na camada Domain de qualquer feature, estas regras são invioláveis:

## API Contract

- **Nunca `PUT`** — somente GET / POST / PATCH / DELETE.
- List endpoints usam **cursor pagination**; `ordering` como input do cliente é rejeitado.
- Unified error envelope: `{errors:[{field, message, code}]}` — `code` é o contrato estável para i18n do client.

## Persistence

- **Soft-delete em toda entidade**; reads padrão excluem trashed; scope "all-records" existe para auditoria.
- **Budget overlap** é enforcado no DB (Postgres range-exclusion constraint), não só na aplicação.
- `value > 0`; `end_date` estritamente após `start_date`.
- **Expense↔Budget é dinâmico por data** — sem FK armazenada; "active budget" = o que cobre `today()`.

## Autenticação

- JWT: access ~15 min, refresh ~30 days com rotation + blacklist.
- Sanctum default NÃO implementa isso — precisa de camada JWT dedicada.

## Estratégias Plugáveis

- Categorização (`keyword | ollama | none`) e chat backends são **estratégias atrás de contracts/interfaces**.
- É aqui que interfaces realmente pagam o custo — não em persistência genérica.

## Queue / Jobs

- Redis/Valkey não pode participar da transação Postgres.
- Regra revisada: **after-commit dispatch + idempotent jobs** (não "enqueue dentro da transaction").

## Decisões de Design

- A spec (OpenSpec catalog) é a fonte de verdade.
- Se o comportamento for ambíguo, **pergunte** ao invés de inventar.
- Refactor ou bugfix que não muda comportamento observável **não** precisa de change no OpenSpec.
