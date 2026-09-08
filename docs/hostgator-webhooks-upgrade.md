# HostGator upgrade: REA CMS 0.1.0-rc.3

This full application package includes the blog, gallery and text-block webhooks,
production PHP dependencies, and compiled browser assets. Composer and Node are
not needed on the hosting account. It also includes the current bundled plugins
and earlier CMS fixes from this workspace.

## Upgrade an existing installation

1. Back up the live application and database before upgrading.
2. In cPanel File Manager, upload and extract the ZIP into a new private application
   directory outside `public_html`. The archive has `app/`, `bin/`, `public/`,
   `vendor/`, and other application folders at its root; create the destination
   directory before extracting it.
3. Copy the existing production `.env` and private `storage/` contents into the
   new application directory. Preserve the existing `APP_KEY`, database settings,
   uploads, installed custom plugins, and any local customizations. Do not replace
   your live `.env` with `.env.example` or regenerate `APP_KEY`.
4. Use PHP 8.2 or newer with the extensions listed in `docs/hosting.md`, including
   cURL. Keep only the application's `public/` directory web-accessible. Follow
   your existing secure document-root arrangement; do not expose the full archive
   contents through a public URL.
5. During a maintenance window, point the site at the new application and sign in
   as a super administrator. Open **Administration → System Upgrade**
   (`/admin/upgrade`) and apply the pending core migrations. For an up-to-date
   installation, the new migration is `008_webhook_delivery_status`.
   The page requires backup confirmation and the administrator's current password.
   If SSH is available, `php bin/migrate.php` is the alternative; use only one path.
6. Open **Administration → Webhooks**, add your website's public HTTPS receiver
   URL, select events, and copy the signing secret into that website's server-side
   configuration. The secret is shown once.
7. Add a cPanel cron job with a schedule of once per minute. Use the PHP CLI
   executable supplied by your hosting account and the absolute application path:

   ```text
   /path/to/php /home/YOUR_CPANEL_USER/path/to/rea-cms/bin/deliver-webhooks.php --limit=100
   ```

   Replace both placeholder paths. CLI PHP must be version 8.2+ and have the same
   required extensions as the website. The command runs a short batch and exits;
   it does not need a permanent worker. Keep existing podcast cron jobs intact.
8. Click **Send test** on the webhook destination. After the next cron run, refresh
   Recent deliveries and confirm a delivered status and a 2xx response. Then test
   a blog edit, text-block edit, and gallery change. Verify login, media, and public
   APIs before ending maintenance mode.

A receiving endpoint must verify the HMAC and timestamp, deduplicate delivery IDs,
and invalidate its content cache or queue a rebuild. A browser cannot receive
webhooks directly. See `docs/webhooks.md` for event names, the payload/signature
contract, retry behavior, and frontend integration.

## First installation

Set up the hosting database, HTTPS, PHP and a document root exposing only `public/`,
then visit `/install`. See `docs/web-setup.md`. The upgrade steps for copying an
existing `.env` apply only to an existing installation.

## Package verification

The matching `.sha256` file records the ZIP checksum. On a trusted workstation:

```sh
php bin/verify-release.php dist/rea-cms-0.1.0-rc.3.zip
```

The archive contains no live `.env`, database export, uploads, logs, or development
PHP dependencies. The package does not run migrations, set up cron, or upload to
HostGator automatically.
