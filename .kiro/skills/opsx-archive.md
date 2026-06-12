---
inclusion: manual
---

# Skill: OpenSpec — Arquivar Change

Valida e arquiva um OpenSpec change completo, atualizando o catálogo canônico de specs.

## Instruções

### Checklist Pré-Arquivo (pare se qualquer item falhar)

- [ ] Todas as tasks em `openspec/changes/<name>/tasks.md` estão checked.
- [ ] `composer test` está verde.
- [ ] `composer lint:test` e `composer analyse` estão verdes.
- [ ] `openspec validate "<name>" --strict` está verde.
- [ ] Os deltas em `specs/**/spec.md` representam o estado **final** de cada capability.

### Execução

1. Rode `openspec archive "<name>"` (move para `openspec/changes/archive/<date>-<name>/` e aplica deltas no catálogo).
2. Confirme que `openspec/specs/<capability>/spec.md` agora reflete o change.
3. Rode `openspec validate --specs` e reporte que está verde.

### Resultado

Reporte a localização final do change arquivado.
