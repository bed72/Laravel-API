---
inclusion: manual
---

# Skill: Up (Test → Commit → Push)

Roda o quality gate, separa as mudanças em commits organizados por escopo, e faz push.

## Instruções

### 1. Quality Gate

Rode em ordem — **pare se qualquer etapa falhar**:

```bash
composer lint:test
composer analyse
composer test
```

Se falhar, reporte o erro e proponha fix. Não prossiga para commit até estar verde.

### 2. Analisar Mudanças

Rode `git status` e `git diff --stat` para entender o que mudou.

Agrupe os arquivos staged/unstaged por **escopo lógico**:

- **feat(<feature>):** arquivos novos/modificados dentro de `app/Features/<Feature>/`, migrations, factories, testes relacionados
- **fix(<feature>):** correções em código existente de uma feature
- **test(<feature>):** apenas testes adicionados/modificados
- **refactor(<scope>):** reorganização sem mudança de comportamento
- **chore:** configs, steering, hooks, CI, dependências
- **docs:** README, CLAUDE.md, comentários

### 3. Commits Separados

Para cada grupo lógico, faça um commit individual:

```bash
git add <arquivos do grupo>
git commit -m "<type>(<scope>): <descrição concisa>"
```

Regras de mensagem:
- Formato: [Conventional Commits](https://www.conventionalcommits.org/)
- Idioma: **inglês**
- Máximo 72 caracteres na primeira linha
- Sem ponto final
- Imperativo: "add", "fix", "update" (não "added", "fixes")

Exemplos:
- `feat(expenses): add soft-delete support`
- `test(expenses): cover validation edge cases`
- `fix(expenses): return 201 on store`
- `chore: add kiro steering and hooks`
- `refactor(expenses): extract date scope to trait`

### 4. Push

```bash
git push
```

Se a branch não tiver upstream, use:
```bash
git push -u origin <branch-name>
```

**Nunca** force push. Se houver conflito, reporte ao invés de resolver automaticamente.

### 5. Resumo

Reporte:
- Quantos commits foram criados
- Resumo de cada um (hash curto + mensagem)
- Branch e remote para onde foi o push

## Regras de Segurança

- **Nunca** commite `.env`, secrets, ou credentials
- **Nunca** push para `main`/`master` diretamente — se estiver nela, pergunte antes
- Verifique se há arquivos sensíveis no diff antes de commitar
- Se houver mudanças não relacionadas que parecem acidentais (node_modules, .idea, etc.), **exclua** e avise
