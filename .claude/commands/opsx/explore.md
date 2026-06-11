---
description: OpenSpec — thinking-partner mode to clarify an idea before it becomes a change
argument-hint: "<rough idea or problem>"
---

Act as a thinking partner to clarify this idea **before** it becomes an OpenSpec change. Do **not**
create any files.

Idea: $ARGUMENTS

1. Ask the questions needed to pin down the behavior (happy + sad paths, edge cases, ownership/auth, error codes).
2. Identify which capability/spec it touches (new vs existing) — check `openspec/specs/` if present.
3. Sketch the GIVEN/WHEN/THEN scenarios it would need, informally, so we can see if any are still vague.
4. End with a recommendation: is it ready for `/opsx:propose "<description>"`, or does it need more thought?

Keep it conversational. The goal is a crisp, testable scope — not artifacts yet.
