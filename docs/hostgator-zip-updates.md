# Update Rea CMS on HostGator with a ZIP

## Enable ZIP updates once

Older installations only have the database upgrade button. Install the release containing the ZIP updater once through cPanel File Manager, following `docs/hostgator-webhooks-upgrade.md`: extract into a private application directory, preserve `.env`, storage and custom plugins, and activate the new release. Keep only `public/` web-accessible. Never extract the whole CMS into `public_html`.

The active website must serve this application's `public/` directory. If you maintain a separate copy of public files in `public_html`, update that copy manually as well; the updater only changes files inside the application directory.

## Subsequent updates from the CMS

1. Back up the application and database in cPanel and verify the backup. Pause CMS cron jobs and schedule a maintenance window.
2. Sign in as a super administrator and open **Administration → System Upgrade** (`/admin/upgrade`).
3. Under **Upload CMS update**, select the built `rea-cms-VERSION.zip`. Open its matching `.sha256` file and paste only the 64-character checksum. Use releases from a trusted source: the checksum detects corruption, not publisher identity. GitHub source ZIPs and plugin ZIPs are not application releases.
4. Confirm the backup, enter your current password and click **Install release ZIP**. Do not close the page while it runs.
5. The page reloads using the installed code. Click **Apply database upgrade** if migrations are pending, confirming your backup and password again. Verify login, content, uploads and API endpoints, then resume cron jobs.

PHP needs write access to application directories and `storage/`; use hosting-account ownership rather than world-writable permissions. The uploader accepts ZIPs up to 64 MB and 256 MB expanded. In cPanel's PHP settings, set `upload_max_filesize` high enough for the archive and `post_max_size` higher than that. Set an execution timeout sufficient for extraction and installation. The page displays the current upload limits. No SSH, Composer or Node is needed on HostGator.

The updater preserves `.env`, all `storage/` data, and files absent from the release, including custom plugins. Files present in the ZIP replace their installed counterparts, so back up local changes to bundled code or templates. Saved API templates in the database remain intact. Older version ZIPs are rejected; reinstalling the same version is allowed. Obsolete files are not deleted automatically.

## Recovery

File backups and a `files.json` journal remain in `storage/backups/upgrade-*`. The updater stages and backs up all replacement files before installing them. Ordinary replacement failures restore files already replaced. Database migrations remain a separate operation and are not rolled back automatically.

If PHP times out or the process is interrupted during replacement, the site stays in maintenance mode (HTTP 503). In cPanel File Manager, read `storage/upgrade-maintenance` to find the affected backup directory. Restore your verified full application backup, or have an administrator restore files from that directory's `old/` tree and remove newly added files listed in `files.json` that have no `old/` counterpart. Preserve production `.env` and storage. Remove `storage/upgrade-maintenance` only after recovery is complete. Restore the database backup too if a subsequent database migration failed and requires rollback. Retain backups until the update is verified, then remove obsolete `upgrade-*` directories to reclaim space.

## Build a release on a development computer

Run `npm run build`, then `php bin/build-release.php <version>` and `php bin/verify-release.php dist/rea-cms-<version>.zip`. The builder includes production PHP dependencies and compiled assets. Transfer the ZIP and matching checksum to the person performing the update.
