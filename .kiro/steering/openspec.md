---
inclusion: fileMatch
fileMatchPattern: "openspec/**"
---

# Trocado — Spec-Driven Development (OpenSpec)

O projeto segue SDD: **escreva a spec antes do código.**

## Estrutura

- Catálogo permanente: `openspec/specs/<capability>/spec.md`
- Changes: `openspec/changes/<name>/` (proposal → design → specs delta → tasks)
- Arquivo: `openspec/changes/archive/<date>-<name>/`

## Formato de Spec

- `### Requirement:` (SHALL/MUST) — cada um com ≥1 `#### Scenario:`
- Scenarios usam **exatamente 4 hashtags** (`####`) no formato GIVEN/WHEN/THEN
- Qualquer comportamento observável pelo mobile client deve passar por um change

## Workflow de Change

1. `/opsx:explore` — clarifica a ideia (thinking-partner, sem artefatos)
2. `/opsx:propose` — cria change (proposal → design → specs delta → tasks)
3. `/opsx:apply` — implementa, percorrendo tasks.md
4. `/opsx:archive` — valida e arquiva, aplicando deltas ao catálogo

## Validação

- Antes de arquivar: `openspec validate "<name>" --strict`
- Catálogo: `openspec validate --specs`

## Regras

- Tasks refletem o stack Laravel (vertical slice, Pest, composer scripts) — **não** Django.
- Refactor/bugfix que não muda comportamento observável **não** precisa de change.
- A spec é a fonte de verdade; quando ambíguo, **pergunte**.
- CLI: `openspec` ou `npx -y @fission-ai/openspec@latest`
