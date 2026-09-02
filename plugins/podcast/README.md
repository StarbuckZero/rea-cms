# Podcast Feeds

The Podcast Feeds plugin stores normalized podcast and episode data in its own
database tables. Public API requests read those tables and lazily refresh feeds
when either their independent refresh interval has elapsed or their weekly
schedule is due.

The default interval is 30 minutes and can be overridden globally or per feed.
Weekly schedules support any combination of Sunday through Saturday, one local
time per selected day, pausing without deleting the saved schedule, and IANA
timezones such as `America/New_York`. `APP_TIMEZONE` supplies the default
timezone for new schedules; when it is absent or invalid the application uses
`UTC`.

Install and enable the bundled plugin:

```sh
php bin/install-reference-podcast.php --enable
```

The following API representations are available while the plugin is enabled:

- `/api/v1/podcast.json`
- `/api/v1/podcast/{feed}.json`
- `/api/v1/podcast/{feed}.html`
- `/api/v1/podcast/{feed}.txt`
- `/api/v1/podcast/{feed}/{episode}.json`

Use a short-lived cron entry to perform scheduled checks without an API request:

```sh
php bin/refresh-podcast-feeds.php
```

Running this command every minute is safe. Each feed is checked only when its
configured interval or timezone-aware weekly schedule is due, and a database
lock prevents concurrent refreshes of the same feed.
