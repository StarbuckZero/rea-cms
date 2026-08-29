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

