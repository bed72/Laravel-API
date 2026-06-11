---
description: OpenSpec — implement the active change, walking its tasks.md
argument-hint: [change-name] (optional; defaults to the active change)
---

Implement an OpenSpec change. If a name is given use it; otherwise pick the active change
(`openspec list --changes`): $ARGUMENTS

1. Read `openspec/changes/<name>/tasks.md`, `proposal.md`, and `specs/**/spec.md`. The specs delta is the
   **contract** — implement exactly the behavior in the scenarios; do not invent validations not in the spec.
2. Work through `tasks.md` in order, following this project's architecture (vertical slice + `FeatureServiceProvider`,
   `$request->user()->id` for ownership, Pest tests for each behavior — see CLAUDE.md and `/new-feature`).
3. As each task completes, flip its `- [ ]` to `- [x]` in `tasks.md`.
4. Write/extend Pest tests so every scenario in the delta has a corresponding test.
5. Run `composer test`, then `composer lint` and `composer analyse`; fix what they flag.

Report what was implemented and which tasks remain. When everything is green and checked, the change is
ready for `/opsx:archive`.
