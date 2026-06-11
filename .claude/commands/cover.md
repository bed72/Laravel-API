---
description: Write or extend Pest tests for a feature following the project's testing conventions
argument-hint: <FeatureName> e.g. Expenses
---

Write thorough Pest tests for the **$1** feature, following
`tests/Feature/Expenses/CreateExpenseTest.php` and `tests/Unit/Expenses/ExpenseServiceTest.php`.

- **Feature tests** (`tests/Feature/$1/`): HTTP behavior of each working endpoint — happy path
  (status, JSON shape, DB assertion) and validation failures. Collapse same-field invalid cases into
  one `it(...)->with([...])` dataset.
- **Unit tests** (`tests/Unit/$1/`): service rules with the repository mocked (Mockery); pure, no Laravel boot.

Read the feature's actual code first — do not assume behavior. Be honest: if a route points to a
non-existent controller method, or a status contradicts the spec (e.g. 200 where create should be 201),
**note it** rather than asserting the wrong thing as correct. Run the new tests at the end if PHP is available.
