# Phase 1 architecture

Phase 1 establishes the smallest secure HTTP and database core. It intentionally
does not include authentication, authorization, API policy, or plugins.

## Request lifecycle

```text
Apache rewrite
  -> public/index.php
  -> environment bootstrap
  -> normalized request
  -> request ID
  -> router/controller closure
  -> HTML or JSON response
  -> security headers
  -> HTTP response
```

Unknown API routes or requests accepting JSON receive the documented JSON error
envelope. Browser requests receive escaped HTML errors. Unhandled exceptions are
logged with method, path, exception class, and request ID; exception messages are
not included in logs or public responses because they may contain secrets.

## Security headers

Every application response includes:

- Content Security Policy restricted to same-origin scripts and styles
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- frame denial through CSP `frame-ancestors` and `X-Frame-Options`
- a cryptographically random `X-Request-ID`

HSTS remains intentionally disabled until production HTTPS and subdomain impact
are verified.

## Database and migrations

PDO uses exception mode, `utf8mb4`, associative fetches, and native prepared
statements. Core SQL migrations live in `database/migrations`, run in filename
order, use the configured safe table prefix, and are recorded with SHA-256
checksums. Editing an applied migration is rejected.

Run migrations with:

```bash
php bin/migrate.php
```

Phase 1 creates the migration tracking table and the minimal core settings
table. Later phases add their tables only when their functionality is built.

## Presentation and assets

Core PHP views are presentation-only and receive an escaping callable. Tailwind
4.3.3 and htmx 4.0.0 are exact development dependencies. The build copies only
compiled/minified assets into `public/assets`; Node is not needed in production.

Theme choices are `system`, `light`, `dark`, and `high-contrast`. A blocking,
same-origin theme script applies a saved preference before page content is
painted. The styles also respect forced colors and reduced motion.
