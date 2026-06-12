---
inclusion: manual
---

# Skill: OpenSpec — Propor Change

Cria um OpenSpec change com proposal, design, specs delta, e tasks.

## Instruções

O `openspec` CLI está disponível (fallback: `npx -y @fission-ai/openspec@latest`).

### Passos

1. Derive um nome **kebab-case** da descrição. Rode `openspec new change "<name>"`.
2. Crie artefatos na **ordem sagrada** — proposal → design → specs → tasks.
   Para cada um, rode `openspec instructions <artifact> --change "<name>" --json` para obter o template,
   então escreva o arquivo em `openspec/changes/<name>/`.

### Artefatos

- `proposal.md` — por quê / o que muda / quais capabilities.
- `design.md` — somente quando há decisões reais de design (concorrência, caching, backend plugável).
- `specs/<capability>/spec.md` — o **delta**, usando `## ADDED|MODIFIED|REMOVED|RENAMED Requirements`.
- `tasks.md` — checklist de implementação (`- [ ]`), agrupado em fases.

### Regras de Spec

- Toda `### Requirement:` tem pelo menos um `#### Scenario:` imediatamente abaixo.
- Scenarios usam **exatamente 4 hashtags** (`####`) e GIVEN/WHEN/THEN (AND opcional).
- Linguagem normativa: **SHALL** / **MUST**.
- Para `## MODIFIED`, copie o requirement **inteiro** e edite.
- `## REMOVED` requer `**Reason:**` e `**Migration:**`; `## RENAMED` usa `FROM:`/`TO:`.

### Tasks

Refletem **este** stack (Laravel vertical slice, Pest, `composer test`/`lint`/`analyse`) — não Django.

### Finalização

`openspec validate "<name>" --strict` — reporte resultado. **Não implemente** — isso é outro skill.
