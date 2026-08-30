# Phase 8 release readiness

Phase 8 converts the Phase 7 application into a reproducible release candidate.
It does not publish, upload, tag, or deploy a release.

## Required gates

GitHub Actions runs the PHP quality suite, Composer and npm advisory audits,
the production asset build, and verifies that generated assets remain current.
A separate database matrix applies core and reference-plugin migrations twice
against MySQL 8.0 and MariaDB 10.11.

The existing security regression suite covers injection-safe query parsing,
stored and reflected output escaping, CSRF, session fixation, authorization and
role boundaries, origin and CORS policy, API scopes, IPv4/IPv6 networks, rate
limits, traversal and hostile ZIP packages, declarative migration namespaces,
unsafe media, webhook SSRF and DNS rebinding, and secret-safe public errors.

## Release artifact

Build and verify a release candidate from a clean checkout:

```bash
composer install
npm ci
npm run build
composer check
composer security-audit
npm audit --omit=dev
php bin/build-release.php 0.1.0-rc.1
php bin/verify-release.php dist/rea-cms-0.1.0-rc.1.zip
```

The builder installs production-only Composer dependencies into an isolated
staging tree and creates a ZIP plus a SHA-256 checksum. It refuses to overwrite
an existing candidate. The verifier rejects checksum changes, development
dependency metadata, and private or development paths.
Empty `.gitkeep` placeholders are retained only to establish private runtime
directories after extraction; no runtime backup, log, session, or upload data
is packaged.

## Release boundary

Creating an artifact is not authorization to publish it. Tags, GitHub releases,
HostGator uploads, production migrations, DNS changes, and live deployment all
remain explicit human-controlled actions.
