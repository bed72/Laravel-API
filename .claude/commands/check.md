---
description: Run the full quality gate — format check, static analysis, and tests
---

Run the project's quality gate in order and report a concise pass/fail summary per step:

1. `composer lint:test` — Pint (style, no writes)
2. `composer analyse` — Larastan/PHPStan
3. `composer test` — Pest suite

If a step fails, show the relevant output and propose fixes. Do **not** fix anything automatically
unless I ask. Stop and report if a tool isn't installed (e.g. `larastan/larastan`).
