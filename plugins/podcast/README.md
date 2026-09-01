# Podcast Feeds

The Podcast Feeds plugin stores normalized podcast and episode data in its own
database tables. Public API requests read those tables and lazily refresh only
feeds whose independent refresh interval has elapsed.

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

Running this command every minute is safe. Each feed is checked only after its
configured interval expires, and a database lock prevents concurrent refreshes
of the same feed.
