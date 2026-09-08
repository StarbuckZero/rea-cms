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
- `/api/v1/podcasts.json` (enabled podcast titles, descriptions, and main images)
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

Episode output includes `audio.durationFormatted` (for example, `1 hour 25 minutes`),
`publishedDate` (`September 8, 2026`), and `publishedTime` (`8:30 PM`). These fields
are available in JSON and as `{podcast.audio.durationFormatted}`,
`{podcast.publishedDate}`, and `{podcast.publishedTime}` in episode HTML/text templates
and the template editors' supported-fields list. Feed JSON includes them on each episode.
The original `audio.durationSeconds` and ISO 8601 `publishedAt` fields are preserved.

Publication formatting uses the feed's configured schedule timezone, including when
interval refresh mode is selected. Missing or invalid timezones fall back to
`APP_TIMEZONE`, then UTC. Missing or invalid episode values produce empty formatted
strings. Duration uses whole minutes (seconds are discarded); durations below one
hour show only minutes, including `0 minutes` for durations below 60 seconds.
