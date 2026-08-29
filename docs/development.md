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
directory. Do not point Apache at the repository root.

The virtual host must allow `.htaccess` overrides for the public directory and
must have `mod_rewrite` and `mod_headers` enabled. Apache must not receive broad
read access to the developer's home directory; use a narrowly scoped ACL if
filesystem traversal permissions are needed.

## Initial setup

```bash
composer install
npm install
cp .env.example .env
php bin/check-platform.php
php bin/configure-local.php
php bin/migrate.php
composer check
composer security-audit
npm run build
```

`composer check` runs manifest validation, PSR-12 checks, PHPStan, and PHPUnit.
Dependency auditing is separate so that a temporary advisory service outage
does not obscure code-quality results.

## Running the Phase 1 application

Enable the existing Apache virtual host and reload Apache:

```bash
sudo a2ensite rea-cms.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Then open `http://rea-cms.test/`. If the Ubuntu default page appears,
`rea-cms.conf` is not enabled or Apache has not been reloaded.

For a temporary development server that does not exercise `.htaccess`:

```bash
php -S 127.0.0.1:8080 -t public
```

The Apache path must still pass before Phase 1 is accepted because clean-URL
routing through `.htaccess` is a production requirement.

## Initial administrator

After applying the Phase 2 migration, create the first super administrator. The
password is read from the process environment and is never accepted as a CLI
argument or printed:

```bash
REA_ADMIN_PASSWORD='use-a-long-unique-password' \
  php bin/create-admin.php --email=admin@example.com --name='Site Administrator'
```

Use at least 12 characters. Remove the shell command from history if your shell
records environment assignments; an interactive secret prompt will replace this
bootstrap mechanism before production packaging.

`bin/configure-local.php` adds missing local-only Phase 2 settings without
overwriting existing values. Review `MAIL_FROM` before testing password-reset
delivery. Production must use HTTPS with `SESSION_SECURE_COOKIE=true`.

## Environment rules

- Real secrets belong only in `.env` or the hosting control panel.
- `.env.example` contains names and safe examples, never working credentials.
- Production uses `APP_ENV=production` and `APP_DEBUG=false`.
- Timestamps are stored and processed in UTC.
- No secrets may be placed under `public/`.
