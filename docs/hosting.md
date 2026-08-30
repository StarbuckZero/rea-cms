# Shared-hosting requirements

Rea CMS targets Apache/PHP/MySQL shared hosting, including HostGator-style
accounts. Only `public/` should be web-accessible. If the hosting account cannot
set a separate document root, deployment must add explicit deny rules for the
application, configuration, vendor, storage, tests, migrations, plugins, and
backup directories before it is considered safe.

## Required capabilities

- PHP 8.2 or newer
- PDO MySQL with native prepared statements
- JSON, mbstring, OpenSSL, fileinfo, and ZIP extensions
- GD or ImageMagick
- MySQL 8 or a verified compatible MariaDB release
- Apache rewrite rules and `.htaccess` support
- HTTPS
- Writable private storage outside the public root where the account permits it
- CLI PHP cron, or a protected HTTPS cron fallback

## Optional capabilities

- OPcache
- ImageMagick
- Brotli compression
- Ability to point the domain directly at `public/`
- SSH and Composer on the hosting account

Composer and Node do not have to be installed on the production account.
Dependencies and compiled assets may be built in development or CI and deployed
as artifacts. No Node server, Docker runtime, Redis service, daemon, or permanent
queue worker is required.

## HostGator discovery checklist

Record the following before Phase 1 database work is accepted:

- PHP version and available extensions
- MySQL version, SQL mode, character set, and collation
- Domain document-root controls
- `mod_rewrite`, `mod_headers`, and permitted `.htaccess` directives
- HTTPS and HSTS suitability
- CLI PHP and cron availability/minimum interval
- Upload, memory, execution-time, and storage limits
- Disabled PHP functions
- GD or ImageMagick availability and limits
- Whether private files can be stored above `public_html`
- Backup and restore facilities supplied by the host

Useful database queries:

```sql
SELECT VERSION();
SELECT @@sql_mode;
SELECT @@character_set_server;
SELECT @@collation_server;
```

## Deployment assumptions

- Production configuration and secrets are created on the host and never
  copied from development.
- The deployment artifact includes `vendor/` and compiled static assets.
- Development-only dependencies are omitted from production artifacts.
- `storage/` permissions are granted narrowly to the PHP/Apache account.
- Application, plugin, upload, backup, and migration files are not executable
  through the web server.
- HSTS is enabled only after HTTPS and subdomain effects are verified.

## HostGator production deployment checklist

1. Build the artifact on the workstation with `composer install --no-dev
   --classmap-authoritative` and `npm ci && npm run build`. Production needs no
   Node process, queue daemon, Redis, Docker, or long-running PHP worker.
2. Run `composer audit --locked`, `npm audit --omit=dev`, and `composer check`
   before packaging. Include `vendor/` and compiled `public/assets/`.
3. Point the domain document root at `public/`. If that is impossible, deny web
   access to `.env`, `app`, `bin`, `config`, `database`, `docs`, `plugins`,
   `resources`, `storage`, `tests`, and `vendor` before uploading application
   files.
4. Create production `.env` on the host with a new `APP_KEY`, production
   database credentials, HTTPS `APP_URL`, secure session cookies, debug disabled,
   exact API origins, and only verified trusted proxies.
5. Keep media, staging, backup, logs, and sessions outside `public_html` where
   possible. Grant only the PHP account read/write access; backup and upload
   files must never be executable or directly downloadable.
6. Select PHP 8.2+ and confirm PDO MySQL, JSON, mbstring, OpenSSL, fileinfo, ZIP,
   and GD or ImageMagick. Confirm MariaDB/MySQL uses `utf8mb4` and a compatible
   SQL mode.
7. Run `php bin/check-platform.php` and `php bin/migrate.php` once from SSH or a
   protected deployment job. Back up and verify restore before migrations.
8. Configure HostGator cron to invoke short-lived CLI PHP job batches. Use a
   lock and reservation expiry; do not configure a permanent worker daemon.
9. Force HTTPS, verify rewrite/security headers, and enable HSTS only after all
   subdomains are confirmed HTTPS-safe. Test login, logout, CSRF, API origins,
   private media, plugin route gating, 404s, and production-safe errors.
10. Verify OPcache and gzip/Brotli when available, check storage quotas and PHP
    upload/memory/time limits, and record a tested rollback procedure.
