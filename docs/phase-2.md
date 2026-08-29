# Phase 2 authentication and authorization

Phase 2 adds identity and administrative access without introducing API tokens
or plugin authorization, which belong to later phases.

## Session model

- Browser cookies contain 256-bit random opaque tokens.
- Only SHA-256 token hashes are stored in the database.
- Login rotates the anonymous session identifier before attaching a user.
- Logout and password reset revoke server-side sessions.
- Cookies are HttpOnly, SameSite Lax, path-scoped, and Secure in production.
- Anonymous sessions provide CSRF protection before authentication.
- Active sessions can be listed and individually revoked by their owner.
- Authentication and administration pages send `Cache-Control: no-store`.

Forwarded client-IP headers are not trusted. The immediate connection address is
used until trusted-proxy configuration is implemented explicitly.

## Authentication

Passwords use PHP's `PASSWORD_DEFAULT`, allowing PHP to strengthen the selected
algorithm over time. Successful login rehashes older hashes when necessary.
Unknown, suspended, deleted, and incorrect-password accounts receive the same
public failure. Login attempts are limited by a hash of normalized email and IP,
with a bounded window and temporary lockout.

The initial super administrator is created only through `bin/create-admin.php`.
The command assigns the built-in role and records a permanent audit event.

## Authorization

Built-in roles are seeded by migration. Permissions are explicit strings such as
`core.admin.access`; controllers query the authorization repository before
rendering protected data. Hiding navigation is never treated as authorization.
The role schema supports custom roles and later plugin-namespaced permissions.

## CSRF and sensitive actions

CSRF tokens are HMAC-derived from the session token and `APP_KEY`. Every Phase 2
POST route validates them before state changes. Reauthentication verifies the
current password and marks the session with a UTC timestamp for later critical
operations.

## Password recovery

Reset tokens are generated from 256 random bits, shown only in the delivery URL,
stored only as SHA-256 hashes, expire after 30 minutes, and are single-use. A
successful reset revokes all sessions for that user. Public responses do not
reveal whether an email address exists.

The token and email are placed in the URL fragment, which is not sent in HTTP
requests. A same-origin script moves them into hidden POST fields and removes the
fragment from browser history immediately, keeping reset tokens out of Apache
request and referrer logs.

The current delivery adapter uses PHP `mail()`, which is compatible with typical
shared hosting. HostGator mail delivery, sender-domain policy, and deliverability
must be verified before production. The delivery interface can be replaced
without changing reset-token handling.

## MFA foundations

The core includes RFC-compatible six-digit TOTP verification, authenticated
AES-256-GCM secret encryption using `APP_KEY`, and one-time recovery-code
generation with password hashes at rest. Enrollment and login challenge screens
remain optional and are not enabled until their complete recovery UX is built.

## Audit events

Login success/failure, logout, CSRF failure, permission denial,
reauthentication, password-reset activity, session revocation, inactive-session
rejection, and bootstrap administrator creation are recorded. Passwords, reset
tokens, session tokens, CSRF tokens, cookies, and email addresses are excluded
from audit metadata.
