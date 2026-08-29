# Rea CMS

Rea CMS (RealTime Efficiency API) is a lightweight, headless-first,
plugin-based content management system for PHP/MySQL shared hosting.

Development is currently in **Phase 0: repository and hosting discovery**.
No application or public entry point exists yet.

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
cp .env.example .env
composer check
composer security-audit
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
```

See [Rea_CMS_Codex_Development_Plan.md](Rea_CMS_Codex_Development_Plan.md)
for the complete gated development plan.
