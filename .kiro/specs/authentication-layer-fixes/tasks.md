# Implementation Plan: Authentication Layer Fixes

## Overview

Fix 9 structural defects in the Authentication feature: dead code from non-existent soft-delete, business logic in middleware, infrastructure coupling in domain service, inconsistent HTTP responses, non-idempotent job, and token rotation duplication. The fix follows the bug condition methodology — exploration tests first (confirm bugs exist), preservation tests (capture baseline), then implementation with verification.

## Tasks

- [x] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Dead Code & Architecture Violations in Authentication Layer
  - **IMPORTANT**: Write this property-based test BEFORE implementing the fix
  - **CRITICAL**: This test MUST FAIL on unfixed code — failure confirms the bugs exist
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior — it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the defects exist
  - **Scoped PBT Approach**: For deterministic structural bugs, scope properties to concrete failing assertions
  - Create test file: `tests/Feature/Authentication/AuthenticationLayerBugConditionTest.php`
  - **Bug Condition C(X)**: The following inputs/states trigger the bugs:
    - C1: `signUp(name, email, password)` where email exists → executes unreachable `findUserIncludingTrashed` branch
    - C2: `signIn(email, password)` where email not found → executes discarded `findUserIncludingTrashed` call
    - C3: Token age > 7 days → `RotateToken` middleware injects `AuthenticationRepositoryInterface` directly
    - C4: `resetPassword(uid, token, newPassword)` → service calls `Password::broker()` facade directly
    - C5: `requestPasswordReset` or `resetPassword` success → returns HTTP 200 with `{}` instead of 204
    - C6: `SendResetPasswordJob` retried → creates duplicate tokens/emails (non-idempotent)
    - C7: `rotateToken` called → hardcodes `'api'` and `now()->addDays(30)` instead of delegating
  - **Expected Behavior Properties** (assertions the test encodes):
    - P1: `AuthenticationRepositoryInterface` MUST NOT define `findUserIncludingTrashed`
    - P2: `DomainError` enum MUST NOT contain `EmailBelongsToDeactivated` or `PasswordWeak` cases
    - P3: `RotateToken` middleware MUST depend on `AuthenticationService`, NOT `AuthenticationRepositoryInterface`
    - P4: `AuthenticationService` MUST inject `PasswordResetBrokerInterface`
    - P5: `requestPasswordReset` and `resetPassword` endpoints MUST return HTTP 204
    - P6: `SendResetPasswordJob` MUST implement `ShouldBeUnique`
    - P7: `AuthenticationRepository::rotateToken` MUST delegate to `$this->createToken()`
  - Test properties using reflection and HTTP assertions on unfixed code
  - Run test on UNFIXED code — expect FAILURE (confirms bugs exist)
  - Document counterexamples found
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.6, 1.7, 1.8, 1.9_

- [x] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Authentication Core Behavior Unchanged
  - **IMPORTANT**: Follow observation-first methodology
  - **CRITICAL**: These tests MUST PASS on unfixed code — passing confirms baseline behavior to preserve
  - Create test file: `tests/Feature/Authentication/AuthenticationPreservationTest.php`
  - **Non-Bug Condition ¬C(X)**: Happy paths and error paths that already work correctly
  - Observe on UNFIXED code and write property-based tests:
    - Observe: `signUp(name, unique_email, password)` → HTTP 201 with `{token, user}` — preserve
    - Observe: `signIn(valid_email, valid_password)` → valid access token with 30-day expiry — preserve
    - Observe: Token age < 7 days → no rotation, request passes through — preserve
    - Observe: Token age > 7 days → rotation, new token in `X-New-Token` header — preserve
    - Observe: `signOut(valid_token)` → invalidates only session token, HTTP 204 — preserve
    - Observe: `logOut(user)` → invalidates all user tokens, HTTP 204 — preserve
    - Observe: `requestPasswordReset(nonexistent_email)` → success silently (non-enumeration) — preserve
    - Observe: `resetPassword(valid_uid, valid_token, new_password)` → changes password, invalidates tokens — preserve
    - Observe: `signUp(existing_email)` → `DomainError::EmailAlreadyRegistered` HTTP 422 — preserve
    - Observe: `signIn(invalid_credentials)` → `DomainError::InvalidCredentials` HTTP 401 — preserve
    - Observe: Rate limiting → HTTP 429 on sign-in and password-reset routes — preserve
  - Write property-based tests using Pest datasets to cover input variations
  - Verify tests PASS on UNFIXED code
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11_

- [x] 3. Fix: Remove soft-delete fiction (dead code and unreachable enum cases)

  - [x] 3.1 Remove `EmailBelongsToDeactivated` and `PasswordWeak` cases from `DomainError` enum
    - Remove the two cases from `app/Core/Domain/Exceptions/DomainError.php`
    - Remove corresponding match arms in `status()`, `message()`, and `field()` methods
    - _Bug_Condition: DomainError enum contains cases never triggered at runtime_
    - _Expected_Behavior: Enum contains only actively-dispatched cases_
    - _Preservation: Existing error dispatching for EmailAlreadyRegistered, InvalidCredentials, ResetTokenInvalid unchanged_
    - _Requirements: 2.9_

  - [x] 3.2 Remove `findUserIncludingTrashed` from interface, repository, and service
    - Remove method from `AuthenticationRepositoryInterface`
    - Remove implementation from `AuthenticationRepository`
    - Remove call and branch in `AuthenticationService::signUp` (the `$trashed` check + `EmailBelongsToDeactivated`)
    - Remove discarded call in `AuthenticationService::signIn`
    - _Bug_Condition: findUserIncludingTrashed is byte-identical to findActiveUserByEmail (no SoftDeletes trait)_
    - _Expected_Behavior: Only findActiveUserByEmail used; no dead soft-delete branches_
    - _Preservation: signUp still rejects existing emails; signIn still rejects invalid credentials_
    - _Requirements: 2.1, 2.2_

- [x] 4. Fix: Move token rotation logic from middleware to AuthenticationService

  - [x] 4.1 Add `rotateTokenIfStale` method to `AuthenticationService`
    - Add constant `TOKEN_ROTATION_DAYS = 7`
    - Implement method: check token age, delegate to `repository->rotateToken()` if stale
    - Returns `?NewAccessToken` (null if not stale)
    - _Bug_Condition: RotateToken middleware injects Repository directly, bypassing Service layer_
    - _Expected_Behavior: Middleware delegates to Service; rotation policy encapsulated in Service_
    - _Preservation: Token rotation behavior unchanged (7-day threshold, header, old token deleted)_
    - _Requirements: 2.3_

  - [x] 4.2 Refactor `RotateToken` middleware to use `AuthenticationService`
    - Replace `AuthenticationRepositoryInterface` dependency with `AuthenticationService`
    - Remove rotation logic, delegate to `$this->service->rotateTokenIfStale($token, $user)`
    - Keep only orchestration logic (check token type, check exists, set header)
    - _Bug_Condition: Middleware contains domain logic (7-day check, rotation decision)_
    - _Expected_Behavior: Middleware only orchestrates request/response_
    - _Preservation: X-New-Token header still set on rotation; non-rotated requests unaffected_
    - _Requirements: 2.3_

- [x] 5. Fix: Abstract `Password::broker()` behind `PasswordResetBrokerInterface`

  - [x] 5.1 Create `PasswordResetBrokerInterface` domain contract
    - Create `app/Features/Authentication/Domain/Contracts/PasswordResetBrokerInterface.php`
    - Define `reset(User $user, string $token, string $newPassword): bool`
    - _Bug_Condition: AuthenticationService uses Password::broker() facade directly_
    - _Expected_Behavior: Domain depends on contract; infrastructure implements it_
    - _Preservation: Reset password functionality unchanged_
    - _Requirements: 2.4_

  - [x] 5.2 Create `PasswordResetBroker` infrastructure implementation
    - Create `app/Features/Authentication/Infrastructure/Services/PasswordResetBroker.php`
    - Wrap `Password::broker()->reset()` call
    - Inject `AuthenticationRepositoryInterface` for `updatePassword` and `deleteAllTokens`
    - _Bug_Condition: Service directly calls Password::broker() and $user->save()_
    - _Expected_Behavior: Implementation handles infrastructure concerns_
    - _Preservation: Password reset with valid token works; invalid token still throws_
    - _Requirements: 2.4_

  - [x] 5.3 Add `updatePassword` to repository interface and implementation
    - Add `updatePassword(User $user, string $newPassword): void` to `AuthenticationRepositoryInterface`
    - Implement in `AuthenticationRepository` (sets password and saves)
    - _Bug_Condition: Service calls $user->save() inline (persistence leaking into domain)_
    - _Expected_Behavior: Persistence delegated to repository_
    - _Preservation: Password is still persisted correctly_
    - _Requirements: 2.4_

  - [x] 5.4 Refactor `AuthenticationService::resetPassword` to use `PasswordResetBrokerInterface`
    - Add `PasswordResetBrokerInterface` to constructor injection
    - Replace `Password::broker()->reset(...)` with `$this->broker->reset($user, $token, $newPassword)`
    - Remove inline closure with `$user->save()` and `deleteAllTokens`
    - _Bug_Condition: Service uses facade directly and manages persistence inline_
    - _Expected_Behavior: Service delegates to broker contract; no infrastructure coupling_
    - _Preservation: resetPassword still throws ResetTokenInvalid for invalid tokens_
    - _Requirements: 2.4_

  - [x] 5.5 Register `PasswordResetBrokerInterface` binding in `AuthenticationServiceProvider`
    - Add `PasswordResetBrokerInterface::class => PasswordResetBroker::class` to `$bindings`
    - _Requirements: 2.4_

- [x] 6. Fix: Standardize responses to 204 for password reset endpoints

  - [x] 6.1 Update `requestPasswordReset` and `resetPassword` to return HTTP 204
    - Replace `response()->json((object) [])` with `response()->json(null, HttpStatusCode::NoContent->value)`
    - _Bug_Condition: Returns HTTP 200 with {} while signOut/logOut return 204 (inconsistency)_
    - _Expected_Behavior: All no-payload success responses return HTTP 204_
    - _Preservation: requestPasswordReset with nonexistent email still returns success silently_
    - _Requirements: 2.6_

- [x] 7. Fix: Make `SendResetPasswordJob` idempotent via `ShouldBeUnique`

  - [x] 7.1 Add `ShouldBeUnique` interface and `uniqueId()` to `SendResetPasswordJob`
    - Implement `ShouldBeUnique` interface
    - Add `uniqueId()` returning `'password-reset-' . $this->user->id`
    - Add `public int $uniqueFor = 300` (5 minute lock)
    - _Bug_Condition: Job retry creates duplicate tokens and sends multiple emails_
    - _Expected_Behavior: ShouldBeUnique prevents concurrent/duplicate executions for same user_
    - _Preservation: First job dispatch still creates token and sends email normally_
    - _Requirements: 2.7_

- [x] 8. Fix: Eliminate duplication in `rotateToken` (delegate to `createToken`)

  - [x] 8.1 Refactor `AuthenticationRepository::rotateToken` to delegate to `$this->createToken()`
    - Replace `$user->createToken('api', expiresAt: now()->addDays(30))` with `$this->createToken($user)`
    - Keep `DB::transaction` wrapper and `$oldToken->delete()`
    - _Bug_Condition: rotateToken hardcodes 'api' and now()->addDays(30) duplicating createToken logic_
    - _Expected_Behavior: rotateToken delegates to createToken; single source of truth for token config_
    - _Preservation: Rotated token still has same name ('api') and same expiry (30 days)_
    - _Requirements: 2.8_

- [x] 9. Verify fixes pass all tests

  - [x] 9.1 Re-run bug condition exploration test
    - **Property 1: Expected Behavior** - Dead Code & Architecture Violations Fixed
    - **IMPORTANT**: Re-run the SAME test from task 1 — do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run `vendor/bin/sail artisan test --compact --filter=AuthenticationLayerBugConditionTest`
    - **EXPECTED OUTCOME**: Test PASSES (confirms all bugs are fixed)
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.6, 2.7, 2.8, 2.9_

  - [x] 9.2 Verify preservation tests still pass
    - **Property 2: Preservation** - Authentication Core Behavior Unchanged
    - **IMPORTANT**: Re-run the SAME tests from task 2 — do NOT write new tests
    - Run `vendor/bin/sail artisan test --compact --filter=AuthenticationPreservationTest`
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all preservation tests still pass after fix (no regressions)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11_

- [x] 10. Checkpoint — Ensure all tests pass
  - Run full test suite: `vendor/bin/sail artisan test --compact`
  - Run Pint formatter: `vendor/bin/sail bin pint --dirty --format agent`
  - Ensure all tests pass, ask the user if questions arise

## Notes

- Project uses Laravel Sail for all commands (`vendor/bin/sail`)
- Pint formatting: `vendor/bin/sail bin pint --dirty --format agent`
- Tests: `vendor/bin/sail artisan test --compact`
- The design confirms that `signOut` already has the correct signature (no dead `$user` param) — no change needed for requirement 2.5
- `Hash::check` remains in Service (stateless utility, not infrastructure coupling) per design decision
- JWT migration is out of scope — this is a bugfix, not a feature change
- Soft-delete is intentionally NOT being implemented — the fiction is being removed

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1", "2"] },
    { "id": 1, "tasks": ["3.1", "3.2"] },
    { "id": 2, "tasks": ["4.1"] },
    { "id": 3, "tasks": ["4.2", "5.1", "5.3"] },
    { "id": 4, "tasks": ["5.2"] },
    { "id": 5, "tasks": ["5.4", "5.5"] },
    { "id": 6, "tasks": ["6.1", "7.1", "8.1"] },
    { "id": 7, "tasks": ["9.1", "9.2"] },
    { "id": 8, "tasks": ["10"] }
  ]
}
```
