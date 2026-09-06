# Web installation and upgrade workflows

Rea CMS provides browser-based setup for shared-hosting accounts where SSH is
limited or inconvenient. Hosting-level work remains outside the application:
select PHP 8.2 or newer, create the MySQL database and user, configure the
domain document root to expose only `public/`, and activate HTTPS before using
the installer.

## First-time installation

When the application root does not contain `.env`, all dynamic requests are
redirected to `/install`. The page:

1. checks the PHP version, required extensions, image library, private storage,
   and application-directory permissions;
2. accepts the HTTPS site origin, timezone, sender address, existing database
   credentials and table prefix;
3. accepts the first administrator's name, email and password;
4. verifies a single-use, 30-minute setup token;
5. connects with native PDO prepared statements and refuses a database that
   already contains tables using the selected prefix;
6. applies the checksum-protected core migrations and creates the first super
   administrator;
7. creates `.env` atomically with mode `0600`, records installation metadata in
   private storage, and redirects to the login page.

The database and administrator passwords are never redisplayed. Database
driver errors are replaced with a generic public message. A failed attempt that
already created database tables can be resumed only when the non-secret setup
fingerprint in `storage/install-pending.json` matches the new submission. On a
resumed attempt, the submitted administrator profile and password replace the
partially-created values.

After installation, `/install` is not part of the normal application router and
returns the standard 404 response. Do not remove `.env` from a live deployment;
restore it from a protected backup instead.

## Application upgrades

The browser page intentionally does not upload or overwrite application code.
Deploy and verify a new release using the release runbook, preserve the current
production `.env` and private uploads, and switch the document root or release
pointer during a maintenance window. Then:

1. sign in as a super administrator;
2. open **Administration → System Upgrade** or `/admin/upgrade`;
3. review the application version and exact pending migration names;
4. confirm that current database and application backups were verified;
5. enter the current administrator password and apply the upgrade;
6. verify the health endpoint, authentication, content, plugins and logs before
   ending maintenance mode.

The upgrade route verifies migration checksums before presenting or applying a
plan. It uses `storage/upgrade.lock` to reject concurrent runs, records success
in the audit log, and never attempts an automatic down migration. If it stops,
keep maintenance mode active, preserve the failed release and logs, and follow
the rollback procedure in `docs/release-runbook.md`.

The equivalent CLI command remains:

```bash
php bin/migrate.php
```

Use either the browser workflow or the CLI for one deployment, not both at the
same time.
