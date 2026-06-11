---
description: OpenSpec — create a change with proposal, design, specs delta, and tasks
argument-hint: "<description of what to build>"
---

Create an OpenSpec change for: $ARGUMENTS

The `openspec` CLI is available (fallback: `npx -y @fission-ai/openspec@latest`). Steps:

1. Derive a **kebab-case** change name from the description. Run `openspec new change "<name>"`.
2. Create the artifacts in the **sacred order** — proposal → design → specs → tasks (each depends on the
   prior). For each, run `openspec instructions <artifact> --change "<name>" --json` to get the template,
   then write the file under `openspec/changes/<name>/`.
   - `proposal.md` — why / what changes / which capabilities.
   - `design.md` — only when there are real design decisions (concurrency, caching, pluggable backend).
   - `specs/<capability>/spec.md` — the **delta**, using `## ADDED|MODIFIED|REMOVED|RENAMED Requirements`.
   - `tasks.md` — implementation checklist (`- [ ]`), grouped in phases.

Spec authoring rules (the validator enforces these):
- Every `### Requirement:` has at least one `#### Scenario:` immediately below it.
- Scenarios use **exactly 4 hashtags** (`####`) and GIVEN/WHEN/THEN (AND optional).
- Normative wording is **SHALL** / **MUST**. Each scenario must be a concrete, testable case.
- For `## MODIFIED`, paste the **entire** requirement (all scenarios) and edit — archiving keeps only what's here.
- `## REMOVED` requires `**Reason:**` and `**Migration:**`; `## RENAMED` uses `FROM:`/`TO:`.

Tasks must reflect **this** project's stack (Laravel vertical slice, Pest, `composer test`/`lint`/`analyse`),
not Django. Finish with `openspec validate "<name>" --strict` and report the result. Do **not** implement yet —
that's `/opsx:apply`.
