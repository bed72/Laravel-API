---
description: OpenSpec — validate and archive a completed change, updating the canonical specs
argument-hint: [change-name] (optional; defaults to the active change)
---

Archive a completed OpenSpec change: $ARGUMENTS

Run the pre-archive checklist first and **stop** if any item fails:

- [ ] All tasks in `openspec/changes/<name>/tasks.md` are checked.
- [ ] `composer test` is green.
- [ ] `composer lint:test` and `composer analyse` are green.
- [ ] `openspec validate "<name>" --strict` is green.
- [ ] The deltas in `specs/**/spec.md` represent the **final** state of each capability (not a draft).

Then:

1. Run `openspec archive "<name>"` (moves it to `openspec/changes/archive/<date>-<name>/` and applies the
   deltas into the canonical catalog).
2. Confirm `openspec/specs/<capability>/spec.md` now reflects the change.
3. Run `openspec validate --specs` and report it's still green.

Report the final location of the archived change.
