# Rea CMS

Rea CMS (RealTime Efficiency API) is a lightweight, headless-first,
plugin-based content management system for PHP/MySQL shared hosting.

Development is currently in **Phase 8: Release readiness**. Automated quality
and MySQL/MariaDB compatibility gates, verified production artifacts, and a
recovery-aware deployment runbook prepare the completed CMS for its first
release candidate without publishing or deploying it.

## Requirements

- PHP 8.2 or newer
- Composer 2
- MySQL 8 or a compatible MariaDB release
- Apache 2.4 with `mod_rewrite`
- Node.js 24 for local asset builds (not required in production)

Required PHP extensions are checked with:

```bash
php bin/check-platform.php
```

## Local setup

```bash
git clone https://github.com/StarbuckZero/rea-cms.git
cd rea-cms
nvm install
nvm use
composer install
npm install
cp .env.example .env
php bin/migrate.php
php bin/configure-local.php
composer check
composer security-audit
npm run build
```

Set the local database credentials in `.env`. The `.env` file is ignored by
Git and must never be committed.

Detailed workstation, database, Apache, and validation instructions are in
[docs/development.md](docs/development.md). Shared-hosting requirements and
deployment assumptions are in [docs/hosting.md](docs/hosting.md).

## Quality commands

```bash
composer validate-config
composer cs
composer analyse
composer test
composer check
composer security-audit
npm run build
```

Local routes:

- `/` — server-rendered home page
- `/fragments/welcome` — htmx fragment example
- `/health` — non-sensitive JSON health response
- `/login` — administrator sign-in
- `/forgot-password` — password reset request
- `/admin` — authenticated and permission-protected administration
- `/admin/plugins` — super-administrator plugin installation, lifecycle, backup, and removal
- `/api/v1/status.json` — same-origin JSON API platform status
- `/api/v1/status.html` — same-origin HTML API platform status
- `/api/v1/status.txt` — same-origin plain-text API platform status

The bundled Podcast Feed plugin can be installed and enabled with:

```bash
php bin/install-reference-podcast.php --enable
```

It adds `/cms/podcast`, cached podcast APIs under `/api/v1/podcast`, and the
short-lived `php bin/refresh-podcast-feeds.php` cron command. See
[plugins/podcast/README.md](plugins/podcast/README.md) for endpoint details.

The default API policy requires an exact configured `Origin` header. For
example:

```bash
curl -H 'Origin: http://rea-cms.test' http://rea-cms.test/api/v1/status.json
```

Additional exact origins can be comma-separated in `API_ALLOWED_ORIGINS`.
Trusted reverse-proxy networks can be comma-separated in `TRUSTED_PROXIES`;
forwarded client addresses are ignored unless the immediate peer matches one
of those networks.

Create a named, scoped API token after migrating with:

```bash
php bin/create-api-token.php --name='Local integration' --scopes='status:read'
```

The plaintext token is displayed once. Only its SHA-256 hash is stored.

See [Rea_CMS_Codex_Development_Plan.md](Rea_CMS_Codex_Development_Plan.md)
for the complete gated development plan.

The declarative package format and lifecycle guarantees are documented in
[docs/phase-4.md](docs/phase-4.md).

The content, media, and job boundaries are documented in
[docs/phase-5.md](docs/phase-5.md).

The Blog package, routes, installer, and integration boundaries are documented
in [docs/phase-6.md](docs/phase-6.md).

Gallery, backup/restore, webhook, accessibility, and performance guarantees are
documented in [docs/phase-7.md](docs/phase-7.md).

Release gates and the deployment boundary are documented in
[docs/phase-8.md](docs/phase-8.md) and [docs/release-runbook.md](docs/release-runbook.md).
