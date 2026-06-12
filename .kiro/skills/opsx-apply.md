---
inclusion: manual
---

# Skill: OpenSpec — Aplicar Change

Implementa um OpenSpec change, percorrendo seu `tasks.md`.

## Instruções

1. Leia `openspec/changes/<name>/tasks.md`, `proposal.md`, e `openspec/specs/**/spec.md`.
   O delta de specs é o **contrato** — implemente exatamente o comportamento dos cenários; não invente validações fora da spec.

2. Trabalhe `tasks.md` em ordem, seguindo a arquitetura do projeto:
   - Vertical slice + ServiceProvider
   - `$request->user()->id` para ownership
   - Pest tests para cada cenário

3. Conforme cada task é concluída, flip `- [ ]` → `- [x]` em `tasks.md`.

4. Escreva/estenda testes Pest para que cada cenário do delta tenha um teste correspondente.

5. Rode quality gate:
   - `composer test`
   - `composer lint`
   - `composer analyse`
   Corrija o que flagarem.

6. Reporte o que foi implementado e quais tasks restam.

Quando tudo estiver verde e checado, o change está pronto para arquivamento.
