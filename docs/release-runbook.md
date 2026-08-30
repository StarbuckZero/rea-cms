# Release, deployment, and rollback runbook

## Before deployment

1. Start from a reviewed commit with a clean worktree and passing Phase 8 gates.
2. Verify the release ZIP with `bin/verify-release.php` on a trusted workstation.
3. Record the artifact checksum, commit, PHP version, database version, and
   currently deployed release.
4. Export an integrity-checked application backup and a hosting-provider SQL
   backup. Test restoration into a separate empty database.
5. Copy the current application directory to a timestamped, non-public release
   directory. Do not overwrite the last known-good copy.

## Deploy

1. Upload the verified ZIP outside the public document root and extract it into
   a new versioned directory.
2. Copy the production `.env` into that directory without printing its values.
3. Preserve production uploads outside the release directory or attach the
   existing private upload path with the hosting account's supported mechanism.
4. Run `php bin/check-platform.php`, then put the site into the hosting account's
   maintenance mode or use its shortest practical maintenance window.
5. Run `php bin/migrate.php` once. Never edit a recorded migration in place.
6. Change the document root or release pointer to the new `public/` directory.
7. Verify `/health`, login and logout, public Blog pages, API denial/allow paths,
   media access, plugin state, error handling, and logs. End maintenance mode.

## Roll back

1. Re-enable maintenance mode and preserve the failed release and its logs.
2. Point the document root or release pointer back to the recorded last
   known-good directory.
3. If a migration changed data incompatibly, restore the pre-deployment SQL and
   application backup. Do not attempt an improvised destructive down migration.
4. Recheck `/health`, authentication, content reads, media, and logs before
   ending maintenance mode.
5. Record the failure, affected release checksum, rollback time, and follow-up.

No production step requires Node, npm, Docker, Redis, or a permanent worker.
Short-lived cron invocations remain the supported background-job mechanism.
