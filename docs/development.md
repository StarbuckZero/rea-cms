# Development setup

## Supported workstation baseline

The initial development workstation is Linux Mint 22.3 (Ubuntu 24.04 base)
with:

- PHP 8.3
- Composer 2.7 or newer
- MariaDB 10.11 for local development
- Apache 2.4
- Node.js 24 managed by nvm

MariaDB is suitable for daily local development, but database migrations and
integration tests must also be run against the production MySQL version before
a release. Rea CMS must avoid vendor-specific SQL unless it is isolated and
tested for both database engines.

## PHP extensions

Required:

- PDO and PDO MySQL
- JSON
- mbstring
- OpenSSL
- fileinfo
- ZIP
- GD or ImageMagick

Recommended for development and later phases:

- curl
- DOM/XML
- intl

Run `php bin/check-platform.php` to validate the required baseline.

## Node.js

Node is a development and CI dependency only. Production does not require a
Node process.

```bash
nvm install
nvm use
node --version
npm --version
```

The repository's `.nvmrc` pins the supported major version. Tailwind and other
asset dependencies will be pinned in Phase 1 when the first asset pipeline is
implemented.

## Database

Create separate development and test databases using `utf8mb4`:

```sql
CREATE DATABASE rea_cms_dev
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE DATABASE rea_cms_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Use a dedicated local account and grant it access only to these databases.
Copy `.env.example` to `.env` and set its database credentials. Never commit
the resulting `.env` file.

Record the local database behavior with:

```sql
SELECT VERSION();
SELECT @@sql_mode;
SELECT @@character_set_server;
SELECT @@collation_server;
```

Run the same statements on HostGator before database-dependent Phase 1 work is
accepted.

## Apache

The local virtual host document root must be the repository's `public/`
directory. That directory will be added in Phase 1 with the front controller;
do not point Apache at the repository root.

The virtual host must allow `.htaccess` overrides for the public directory and
must have `mod_rewrite` and `mod_headers` enabled. Apache must not receive broad
read access to the developer's home directory; use a narrowly scoped ACL if
filesystem traversal permissions are needed.

## Initial setup

```bash
composer install
cp .env.example .env
php bin/check-platform.php
composer check
composer security-audit
```

`composer check` runs manifest validation, PSR-12 checks, PHPStan, and PHPUnit.
Dependency auditing is separate so that a temporary advisory service outage
does not obscure code-quality results.

## Environment rules

- Real secrets belong only in `.env` or the hosting control panel.
- `.env.example` contains names and safe examples, never working credentials.
- Production uses `APP_ENV=production` and `APP_DEBUG=false`.
- Timestamps are stored and processed in UTC.
- No secrets may be placed under `public/`.
