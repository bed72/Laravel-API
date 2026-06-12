# Requirements Document

## Introduction

Token-based authentication system for the Trocado personal-finance API using Laravel Sanctum with opaque tokens. This feature provides user registration, sign-in, transparent periodic token rotation, sign-out (single device), log-out (all devices), and password reset. Authentication uses a single opaque token per session stored as a SHA-256 hash in Sanctum's `personal_access_tokens` table (Postgres). Tokens have a 30-day lifetime and rotate transparently every 7 days of use — the client receives a replacement token via a custom response header. Revocation is immediate via database deletion; every request validates the token through a database lookup, which is acceptable at Trocado's scale.

## Glossary

- **Auth_System**: The authentication feature within Trocado, responsible for registration, sign-in, token issuance, transparent token rotation, sign-out, log-out, and password reset.
- **Opaque_Token**: A cryptographically random bearer token with no embedded claims. The plaintext token is returned to the client once at issuance; the server stores only its SHA-256 hash in the `personal_access_tokens` table.
- **Token_Rotation**: A transparent server-side mechanism that replaces a token older than 7 days with a new one. The new token is delivered in the `X-New-Token` response header; the old token is deleted from the database.
- **Error_Envelope**: The unified JSON error response format `{errors: [{field, message, code}]}` used for all ≥400 responses.
- **Rate_Limiter**: Per-IP request throttling applied to authentication endpoints, returning 429 with a Retry-After header when exceeded.
- **Password_Reset_Token**: A one-time token sent via email that authorizes setting a new password for a user account.
- **User**: A registered person identified by email address, represented by the existing User model at `app/Features/Users/Domain/Models/User.php`.
- **Sign_In**: The action of authenticating with email and password to obtain a token (`POST /auth/sign-in`). Applies to a single session.
- **Sign_Out**: The action of revoking only the current token used in the request (`POST /auth/sign-out`). Invalidates a single device/session.
- **Log_Out**: The action of revoking all tokens for the authenticated user (`POST /auth/logout`). Invalidates all devices/sessions.

## Requirements

### Requirement 1: User Registration

**User Story:** As a new user, I want to register with my email, name, and password, so that I can create an account and immediately receive an authentication token.

#### Acceptance Criteria

1. WHEN a registration request is received with a valid email (RFC 5322 format), a name between 1 and 128 characters, and a password of at least 8 characters, THE Auth_System SHALL create a new User, issue an Opaque_Token (expiring in 30 days), and return a 201 response containing `{token, user{id, email, name}}`.
2. WHEN a registration request contains an email already associated with an active account, THE Auth_System SHALL return a 422 response with error code `email_already_registered`.
3. WHEN a registration request contains an email associated with a deactivated (anonymized) account, THE Auth_System SHALL return a 422 response with error code `email_belongs_to_deactivated`.
4. WHEN a registration request contains a password shorter than 8 characters, THE Auth_System SHALL return a 422 response with error code `password_weak`.
5. THE Auth_System SHALL store the password only as a secure hash, never in plaintext.
6. IF a registration request is missing any required field (email, name, or password) or contains an empty value for any of them, THEN THE Auth_System SHALL return a 422 response with the unified error envelope identifying each invalid field.
7. IF a registration request contains an email that does not conform to RFC 5322 format or a name that is empty or exceeds 128 characters, THEN THE Auth_System SHALL return a 422 response with the unified error envelope identifying the invalid field.

### Requirement 2: Sign In (Token Issuance)

**User Story:** As a registered user, I want to sign in with my email and password, so that I can obtain a token to access protected API endpoints.

#### Acceptance Criteria

1. WHEN a sign-in request is received at `POST /auth/sign-in` with valid email and password credentials, THE Auth_System SHALL issue an Opaque_Token (expiring in 30 days) and return a 200 response containing `{token}`.
2. IF a sign-in request contains a non-existent email, a wrong password, or an email belonging to a soft-deleted (anonymized) account, THEN THE Auth_System SHALL return a 401 response with error code `invalid_credentials` and `field: null`, using the same response regardless of which condition caused the failure.
3. THE Rate_Limiter SHALL restrict sign-in requests to 5 per minute per IP address.
4. WHEN the sign-in rate limit is exceeded, THE Auth_System SHALL return a 429 response with a `Retry-After` header indicating seconds until the limit resets.
5. IF a sign-in request is missing the email or password field, THEN THE Auth_System SHALL return a 422 response with error code `field_required` and the `field` property set to the name of the missing field.

### Requirement 3: Periodic Token Rotation

**User Story:** As an authenticated user, I want my token to be rotated transparently, so that my session remains secure without requiring manual re-authentication.

#### Acceptance Criteria

1. WHEN an authenticated request is received with an Opaque_Token that was issued more than 7 days ago, THE Auth_System SHALL generate a new Opaque_Token (expiring in 30 days from the current moment), delete the old token from the database, and include the new plaintext token in the `X-New-Token` response header.
2. WHILE an Opaque_Token is less than 7 days old, THE Auth_System SHALL authenticate the request normally without performing rotation.
3. THE Auth_System SHALL perform token rotation atomically within a single database transaction, ensuring no window exists where both the old and new tokens are simultaneously valid.
4. WHEN token rotation occurs, THE Auth_System SHALL process the original request normally and return the expected response body alongside the `X-New-Token` header.
5. IF the token presented in the Authorization header is expired (older than 30 days) or does not match any record in the database, THEN THE Auth_System SHALL return a 401 response with error code `invalid_credentials`.

### Requirement 4: Sign Out (Current Device)

**User Story:** As an authenticated user, I want to sign out from my current device, so that the token used in this session is immediately invalidated and cannot be reused.

#### Acceptance Criteria

1. WHEN a sign-out request is received at `POST /auth/sign-out` with a valid Opaque_Token in the Authorization header, THE Auth_System SHALL delete that specific token from the `personal_access_tokens` table and return a 204 response with no body.
2. IF a sign-out request is received without a valid Opaque_Token in the Authorization header, THEN THE Auth_System SHALL return a 401 response with error code `invalid_credentials`.

### Requirement 5: Log Out (All Devices)

**User Story:** As an authenticated user, I want to log out from all devices at once, so that every active session is immediately invalidated.

#### Acceptance Criteria

1. WHEN a log-out request is received at `POST /auth/logout` with a valid Opaque_Token in the Authorization header, THE Auth_System SHALL delete all tokens for the authenticated User from the `personal_access_tokens` table and return a 204 response with no body.
2. IF a log-out request is received without a valid Opaque_Token in the Authorization header, THEN THE Auth_System SHALL return a 401 response with error code `invalid_credentials`.

### Requirement 6: Password Reset Request

**User Story:** As a user who forgot their password, I want to request a password reset, so that I can regain access to my account.

#### Acceptance Criteria

1. WHEN a password reset request is received with a valid email address, THE Auth_System SHALL return a 200 response with an empty JSON object `{}` regardless of whether the email exists in the system (no user-enumeration).
2. WHEN a password reset request is received for an existing user, THE Auth_System SHALL enqueue a password reset email job using after-commit dispatch; WHEN the email does not belong to any existing user, THE Auth_System SHALL not enqueue any job.
3. WHEN the password reset email job is dispatched, THE Auth_System SHALL include a reset link in the email pointing to `/account/password/reset/<uid>/<token>` (a web page, not the JSON API).
4. THE Rate_Limiter SHALL restrict password reset request and confirm endpoints to 3 per hour per IP address.
5. WHEN the password reset rate limit is exceeded, THE Auth_System SHALL return a 429 response with a `Retry-After` header indicating seconds until the limit resets.
6. WHEN a password reset request is received with a missing or malformed email field, THE Auth_System SHALL return a 422 response with the Error_Envelope format identifying the invalid field.
7. THE Auth_System SHALL ensure the password reset email job is idempotent: processing the same job multiple times SHALL NOT result in duplicate emails being sent to the user.

### Requirement 7: Password Reset Confirmation

**User Story:** As a user who received a reset email, I want to set a new password using the reset token, so that I can regain access with updated credentials.

#### Acceptance Criteria

1. WHEN a password reset confirmation is received with a valid uid, token, and new_password, THE Auth_System SHALL update the user's password, consume the Password_Reset_Token (preventing reuse), delete all existing tokens for that user from the `personal_access_tokens` table, and return a 200 response.
2. IF a password reset confirmation is received with a token that is invalid, expired, or already consumed, THEN THE Auth_System SHALL return a 400 response with error code `reset_token_invalid`.
3. IF a password reset confirmation is received with a new_password shorter than 8 characters, THEN THE Auth_System SHALL return a 422 response with error code `password_weak`.
4. IF a password reset confirmation is received with a uid that does not correspond to an existing user, THEN THE Auth_System SHALL return a 400 response with error code `reset_token_invalid`.

### Requirement 8: Token Characteristics

**User Story:** As a system operator, I want tokens to be securely generated and stored, so that the system is protected against token theft and forgery.

#### Acceptance Criteria

1. THE Auth_System SHALL issue Opaque_Tokens with an expiration time of exactly 30 days (2,592,000 seconds) from the moment of issuance.
2. THE Auth_System SHALL generate Opaque_Tokens using a minimum of 32 cryptographically secure random bytes.
3. THE Auth_System SHALL store only the SHA-256 hash of each Opaque_Token in the `personal_access_tokens` table, never the plaintext value.
4. THE Auth_System SHALL return the plaintext Opaque_Token to the client exactly once: at the moment of issuance (registration, sign-in, or rotation).
5. WHEN an authenticated request is received, THE Auth_System SHALL validate the token by computing its SHA-256 hash and matching it against the `personal_access_tokens` table via database lookup.
6. IF the computed hash does not match any record in the `personal_access_tokens` table or the matched record has an expiration date in the past, THEN THE Auth_System SHALL return a 401 response with error code `invalid_credentials`.

### Requirement 9: Error Response Format

**User Story:** As an API consumer, I want all authentication errors to follow a consistent format, so that I can handle them predictably in client code.

#### Acceptance Criteria

1. THE Auth_System SHALL format all responses with HTTP status ≥400 using the Error_Envelope structure `{errors: [{field, message, code}]}`.
2. THE Auth_System SHALL use stable error codes as the `code` field: `invalid_credentials`, `reset_token_invalid`, `email_already_registered`, `email_belongs_to_deactivated`, `password_weak`, `current_password_incorrect`, `current_password_required`, `new_password_required`. Framework-level codes (`required`, `invalid`, `not_authenticated`, `permission_denied`, `not_found`, `throttled`, `server_error`) SHALL remain valid for framework-originated failures.
3. IF the error is caused by a specific request field, THEN THE Auth_System SHALL set the `field` property to that field's name; otherwise THE Auth_System SHALL set `field` to `null`.
4. IF multiple validation errors exist for a single request field, THEN THE Auth_System SHALL collapse them into one entry in the `errors` array with individual messages joined by "; ".
5. THE Auth_System SHALL write error messages in PT-BR, second person ("você"), free of code identifiers and infrastructure terms, ending with a period. The `code` field SHALL serve as the stable contract for client-side localization.
6. THE Auth_System SHALL transform Laravel's default validation exceptions into the Error_Envelope format before sending the response.

### Requirement 10: Privacy and Telemetry

**User Story:** As a user, I want my personal information protected from error tracking systems, so that my data is not inadvertently exposed to third parties.

#### Acceptance Criteria

1. THE Auth_System SHALL exclude the following HTTP headers from any data forwarded to error reporting or telemetry services: Authorization, Cookie, X-Forwarded-For, X-Real-IP, and Forwarded.
2. THE Auth_System SHALL exclude client IP addresses and server environment variables containing remote addresses from any data forwarded to error reporting or telemetry services.
3. THE Auth_System SHALL exclude request body fields containing credentials or tokens (password, password_confirmation, new_password, current_password, token) from any data forwarded to error reporting or telemetry services.
4. IF an unhandled exception occurs during an authentication request, THEN THE Auth_System SHALL forward the error report with all sensitive fields listed in criteria 1–3 redacted before transmission to external services.

### Requirement 11: Rate Limit Response Format

**User Story:** As an API consumer, I want rate limit responses to include timing information, so that I can implement proper backoff behavior.

#### Acceptance Criteria

1. WHEN any rate limit is exceeded, THE Auth_System SHALL return a 429 HTTP status code with a response body following the Error_Envelope structure using error code `throttled`.
2. WHEN any rate limit is exceeded, THE Auth_System SHALL include a `Retry-After` header containing an integer representing the number of whole seconds remaining until the rate limit window resets.
3. THE Auth_System SHALL apply rate limits on a per-IP-address basis, using the client IP from the `X-Forwarded-For` header when operating behind a trusted reverse proxy.
4. THE Auth_System SHALL include `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers in all rate-limited endpoint responses, indicating the maximum number of requests allowed in the current window and the number of requests remaining respectively.
