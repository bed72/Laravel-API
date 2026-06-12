# Implementation Plan: JWT Authentication (Sanctum Opaque Tokens)

## Overview

Implement token-based authentication for the Trocado API using Laravel Sanctum's opaque token system. The implementation follows the vertical slice architecture under `app/Features/Authentication/`, providing registration, sign-in, token rotation, sign-out, log-out, and password reset. The error envelope transformation is project-wide and lives in `bootstrap/app.php`.

## Tasks

- [x] 1. Error envelope and project-wide infrastructure
  - [x] 1.1 Implement unified error envelope transformation in `bootstrap/app.php`
    - Add exception renderer that transforms all ≥400 responses for `api/*` into `{errors:[{field,message,code}]}`
    - Handle `ValidationException` → 422 (collapse multiple messages per field with "; ")
    - Handle `AuthenticationException` → 401 with code `invalid_credentials`
    - Handle `ThrottleRequestsException` → 429 with code `throttled`, set `Retry-After` header
    - Handle generic `HttpException` → appropriate status with mapped code
    - Handle unhandled exceptions → 500 with `server_error` code, PT-BR message
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6_

  - [ ] 1.2 Write feature tests for error envelope format
    - **Property 19: Unified error envelope format**
    - **Validates: Requirements 9.1, 9.2, 9.3**
    - Test validation errors, authentication errors, throttle errors, and generic HTTP errors all conform to envelope

  - [x] 1.3 Configure Sanctum token expiration in `config/sanctum.php`
    - Set `'expiration' => 43200` (30 days in minutes)
    - _Requirements: 8.1_

- [x] 2. Authentication feature scaffold and service provider
  - [x] 2.1 Create `AuthenticationServiceProvider` with rate limiters and route registration
    - Create `app/Features/Authentication/Infrastructure/Providers/AuthenticationServiceProvider.php`
    - Define `auth-sign-in` rate limiter (5/min per IP) and `auth-password-reset` rate limiter (3/hour per IP)
    - Bind `AuthenticationRepositoryInterface` → `AuthenticationRepository`
    - Register routes via `Route::middleware('api')->prefix('api')->group(...)`
    - Register provider in `bootstrap/providers.php`
    - _Requirements: 2.3, 6.4, 11.3_

  - [x] 2.2 Create `AuthenticationRepositoryInterface` contract
    - Create `app/Features/Authentication/Domain/Contracts/AuthenticationRepositoryInterface.php`
    - Define methods: `findActiveUserByEmail`, `findUserIncludingTrashed`, `createUser`, `createToken`, `deleteToken`, `deleteAllTokens`, `rotateToken`
    - _Requirements: 1.1, 2.1, 3.1, 4.1, 5.1_

  - [x] 2.3 Create `AuthenticationRepository` implementation
    - Create `app/Features/Authentication/Infrastructure/Repositories/AuthenticationRepository.php`
    - Implement all contract methods using Eloquent
    - `rotateToken` must wrap delete + create in a `DB::transaction`
    - Set `expires_at` to `now()->addDays(30)` on all token creation
    - _Requirements: 3.3, 8.1, 8.3_

  - [x] 2.4 Create `AuthenticationService` with registration, sign-in, sign-out, log-out, and password reset methods
    - Create `app/Features/Authentication/Domain/Services/AuthenticationService.php`
    - `register()`: create user with hashed password, issue token, return token + user data
    - `signIn()`: find active user, verify password (generic failure for non-existent/wrong/soft-deleted), issue token
    - `signOut()`: delete current token
    - `logOut()`: delete all user tokens
    - `requestPasswordReset()`: dispatch `SendResetPasswordJob` only if active user exists (after-commit)
    - `resetPassword()`: validate reset token via Password broker, update password, delete all tokens
    - _Requirements: 1.1, 1.2, 1.3, 1.5, 2.1, 2.2, 4.1, 5.1, 6.1, 6.2, 7.1_

- [x] 3. Registration endpoint
  - [x] 3.1 Create `RegisterRequest` form request
    - Create `app/Features/Authentication/Http/Requests/RegisterRequest.php`
    - Validate: `email` (required, RFC 5322, max 255), `name` (required, 1–128 chars), `password` (required, min 8)
    - Custom error messages in PT-BR with appropriate error codes
    - _Requirements: 1.4, 1.6, 1.7_

  - [x] 3.2 Create `AuthenticationController` with `register` method
    - Create `app/Features/Authentication/Http/Controllers/AuthenticationController.php`
    - Slim controller: inject `AuthenticationService`, delegate to service, return `JsonResponse` with 201
    - _Requirements: 1.1_

  - [x] 3.3 Create routes file with registration route
    - Create `app/Features/Authentication/Http/Routes/Routes.php`
    - Add `POST /auth/register` route (public, no auth middleware)
    - _Requirements: 1.1_

  - [ ]* 3.4 Write property tests for registration
    - **Property 1: Registration round-trip**
    - **Property 2: Duplicate email rejection**
    - **Property 3: Password minimum length enforcement (registration)**
    - **Property 4: Password never stored in plaintext**
    - **Property 5: Registration validation rejects invalid input**
    - **Validates: Requirements 1.1, 1.2, 1.4, 1.5, 1.6, 1.7**

- [x] 4. Checkpoint - Ensure registration works end-to-end
  - Ensure all tests pass, ask the user if questions arise.

- [x] 5. Sign-in endpoint
  - [x] 5.1 Create `SignInRequest` form request
    - Create `app/Features/Authentication/Http/Requests/SignInRequest.php`
    - Validate: `email` (required), `password` (required)
    - Custom error messages in PT-BR
    - _Requirements: 2.5_

  - [x] 5.2 Add `signIn` method to `AuthenticationController` and sign-in route
    - Add `POST /auth/sign-in` route with `throttle:auth-sign-in` middleware
    - Controller delegates to `AuthenticationService::signIn()`, returns 200 with `{token}`
    - _Requirements: 2.1, 2.3_

  - [ ]* 5.3 Write property tests for sign-in
    - **Property 6: Sign-in round-trip**
    - **Property 7: Sign-in failure indistinguishability**
    - **Validates: Requirements 2.1, 2.2**

  - [ ]* 5.4 Write feature tests for sign-in rate limiting
    - Test that 6th request within 1 minute returns 429 with `Retry-After` header
    - Test `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers
    - **Property 20: Rate limit response format (sign-in)**
    - **Validates: Requirements 2.3, 2.4, 11.1, 11.2, 11.4**

- [x] 6. Sign-out and log-out endpoints
  - [x] 6.1 Add `signOut` and `logOut` methods to `AuthenticationController` and routes
    - Add `POST /auth/sign-out` and `POST /auth/logout` routes with `auth:sanctum` middleware
    - `signOut` → delete current token → 204
    - `logOut` → delete all tokens → 204
    - _Requirements: 4.1, 5.1_

  - [ ]* 6.2 Write property tests for sign-out and log-out
    - **Property 11: Sign-out invalidates exactly the current token**
    - **Property 12: Log-out invalidates all user tokens**
    - **Validates: Requirements 4.1, 5.1**

- [x] 7. Token rotation middleware
  - [x] 7.1 Create `RotateToken` middleware
    - Create `app/Features/Authentication/Http/Middleware/RotateToken.php`
    - Check if token `created_at` is older than 7 days
    - If yes: rotate atomically in DB transaction (delete old, create new with 30-day expiry)
    - Set `X-New-Token` response header with new plaintext token
    - If token is not due for rotation, pass through without modification
    - _Requirements: 3.1, 3.2, 3.3, 3.4_

  - [x] 7.2 Register `RotateToken` middleware on authenticated routes
    - Apply middleware to `auth:sanctum` route group in `Routes.php`
    - Also apply globally to all `auth:sanctum` routes via `bootstrap/app.php` or route middleware alias
    - _Requirements: 3.1_

  - [ ]* 7.3 Write property tests for token rotation
    - **Property 8: Token rotation threshold**
    - **Property 9: Rotation preserves request processing**
    - **Property 10: Expired or invalid token rejection**
    - **Validates: Requirements 3.1, 3.2, 3.4, 3.5**

- [x] 8. Checkpoint - Ensure auth flow and rotation work
  - Ensure all tests pass, ask the user if questions arise.

- [x] 9. Password reset flow
  - [x] 9.1 Create `SendResetPasswordJob` and `ResetPasswordNotification`
    - Create `app/Features/Authentication/Domain/Jobs/SendResetPasswordJob.php` (queued, after-commit dispatch)
    - Create `app/Features/Authentication/Domain/Notifications/ResetPasswordNotification.php` with PT-BR email template
    - Reset link format: `/account/password/reset/{uid}/{token}`
    - Job must be idempotent
    - _Requirements: 6.2, 6.3, 6.7_

  - [x] 9.2 Create `PasswordResetRequest` and `PasswordResetConfirmRequest` form requests
    - `PasswordResetRequest`: validate `email` (required, valid format)
    - `PasswordResetConfirmRequest`: validate `uid` (required), `token` (required), `new_password` (required, min 8)
    - Custom PT-BR error messages with appropriate codes
    - Create at `app/Features/Authentication/Http/Requests/`
    - _Requirements: 6.6, 7.3_

  - [x] 9.3 Add password reset methods to `AuthenticationController` and routes
    - Add `POST /auth/password/request` route with `throttle:auth-password-reset` middleware
    - Add `POST /auth/password/reset` route with `throttle:auth-password-reset` middleware
    - `requestPasswordReset` → always returns 200 `{}` (non-enumeration)
    - `resetPassword` → validates token, updates password, revokes all tokens, returns 200
    - _Requirements: 6.1, 7.1, 7.2, 7.4_

  - [ ]* 9.4 Write property tests for password reset
    - **Property 13: Password reset non-enumeration**
    - **Property 14: Password reset job conditional dispatch**
    - **Property 15: Password reset confirmation round-trip**
    - **Property 16: Invalid reset token rejection**
    - **Validates: Requirements 6.1, 6.2, 7.1, 7.2, 7.4**

- [x] 10. Token characteristics and privacy
  - [x] 10.1 Write feature tests for token characteristics
    - **Property 17: Token expiration set to exactly 30 days**
    - **Property 18: Token stored as SHA-256 hash only**
    - **Validates: Requirements 8.1, 8.3**

  - [x] 10.2 Configure sensitive data redaction for error reporting
    - Add `dontFlash` configuration for credential fields in exception handler
    - Configure header redaction: `Authorization`, `Cookie`, `X-Forwarded-For`, `X-Real-IP`, `Forwarded`
    - Configure body field redaction: `password`, `password_confirmation`, `new_password`, `current_password`, `token`
    - _Requirements: 10.1, 10.2, 10.3, 10.4_

- [x] 11. Final checkpoint - Full integration verification
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases
- The design uses Sanctum opaque tokens (NOT JWT despite the spec name)
- Error envelope transformation is project-wide; all other tasks are Authentication-feature-specific
- After-commit dispatch is used for the password reset job (Valkey can't participate in Postgres transactions)

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.3", "2.2"] },
    { "id": 1, "tasks": ["1.2", "2.1", "2.3"] },
    { "id": 2, "tasks": ["2.4"] },
    { "id": 3, "tasks": ["3.1", "3.3", "5.1"] },
    { "id": 4, "tasks": ["3.2"] },
    { "id": 5, "tasks": ["3.4", "5.2"] },
    { "id": 6, "tasks": ["5.3", "5.4", "6.1"] },
    { "id": 7, "tasks": ["6.2", "7.1"] },
    { "id": 8, "tasks": ["7.2"] },
    { "id": 9, "tasks": ["7.3", "9.1", "9.2"] },
    { "id": 10, "tasks": ["9.3"] },
    { "id": 11, "tasks": ["9.4", "10.1", "10.2"] }
  ]
}
```
