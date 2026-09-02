<?php

declare(strict_types=1);

use ReaCms\Podcast\PodcastFeed;
use ReaCms\Podcast\PodcastSchedule;
use ReaCms\Podcast\PodcastScheduleDay;

/** @var callable(mixed): string $escape */
/** @var PodcastFeed|null $feed */
/** @var string $csrfToken */
/** @var PodcastSchedule $schedule */
/** @var list<string> $timezones */
/** @var string $defaultScheduleTimezone */
$editing = $feed !== null;
$refreshMode = $feed?->refreshMode ?? PodcastSchedule::MODE_INTERVAL;
$scheduleTimezone = $feed?->scheduleTimezone ?? $defaultScheduleTimezone;
$scheduledDays = [];
foreach ($feed?->scheduleDays ?? [] as $scheduledDay) {
    $scheduledDays[$scheduledDay->dayOfWeek] = $scheduledDay->localTime;
}
$timezoneError = $feed === null ? null : $schedule->configurationError($feed);
?>
<section>
    <p class="eyebrow">Podcast Feeds</p>
    <h1 class="mt-3 text-3xl font-bold"><?= $editing ? 'Edit feed' : 'Add feed' ?></h1>
    <p class="mt-2 text-secondary">
        Configure the RSS source, refresh schedule, and whether this podcast appears in the public API.
    </p>
    <form class="panel mt-8 space-y-6" method="post"
          action="<?= $editing ? '/cms/podcast/' . (int) $feed->id : '/cms/podcast' ?>">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <div>
            <label class="form-label" for="podcast-slug">Feed slug</label>
            <input class="form-input" id="podcast-slug" required name="slug"
                   value="<?= $escape($feed?->slug ?? '') ?>" placeholder="my-podcast"
                   aria-describedby="podcast-slug-help">
            <p class="mt-2 text-sm text-secondary" id="podcast-slug-help">
                Used in this feed's API URL, for example <code>/api/v1/podcast/my-podcast.json</code>.
            </p>
        </div>

        <div>
            <label class="form-label" for="podcast-rss-url">RSS feed URL</label>
            <input class="form-input" id="podcast-rss-url" required type="url" name="rss_url"
                   value="<?= $escape($feed?->rssUrl ?? '') ?>"
                   placeholder="https://example.com/podcast.xml" aria-describedby="podcast-rss-help">
            <p class="mt-2 text-sm text-secondary" id="podcast-rss-help">
                Enter the original public HTTP or HTTPS podcast feed URL.
            </p>
        </div>

        <fieldset class="plugin-card space-y-4">
            <legend class="form-label">Update mode</legend>
            <label class="flex items-start gap-3">
                <input class="mt-1" type="radio" name="refresh_mode" value="interval"
                    <?= $refreshMode === PodcastSchedule::MODE_INTERVAL ? 'checked' : '' ?>>
                <span><span class="font-semibold">Time interval</span><br>
                    <span class="text-sm text-secondary">Refresh after a configurable number of minutes.</span></span>
            </label>
            <label class="flex items-start gap-3">
                <input class="mt-1" type="radio" name="refresh_mode" value="schedule"
                    <?= $refreshMode === PodcastSchedule::MODE_SCHEDULE ? 'checked' : '' ?>>
                <span><span class="font-semibold">Weekly schedule</span><br>
                    <span class="text-sm text-secondary">Refresh only on selected local weekdays.</span></span>
            </label>
        </fieldset>

        <fieldset class="plugin-card space-y-4">
            <legend class="form-label">Time interval settings</legend>
            <div>
                <label class="form-label" for="podcast-refresh-interval">Refresh interval override</label>
                <input class="form-input" id="podcast-refresh-interval" type="number" min="1" max="1440"
                       name="refresh_interval" value="<?= $escape($feed?->refreshIntervalMinutes ?? '') ?>"
                       placeholder="Use global default" aria-describedby="podcast-refresh-help">
                <p class="mt-2 text-sm text-secondary" id="podcast-refresh-help">
                    Optional, in minutes. Leave blank to use the global Podcast Feed setting (30 minutes by default).
                </p>
            </div>
            <label class="flex items-start gap-3">
                <input class="mt-1" type="checkbox" name="automatic_refresh" value="1"
                    <?= !$editing || $feed->automaticRefresh ? 'checked' : '' ?>>
                <span>
                    <span class="font-semibold">Enable interval updates</span><br>
                    <span class="text-sm text-secondary">Used when time interval mode is selected.</span>
                </span>
            </label>
        </fieldset>

        <fieldset class="plugin-card space-y-4">
            <legend class="form-label">Weekly schedule settings</legend>
            <?php if ($timezoneError !== null) : ?>
                <div class="panel" role="alert">
                    <strong>Schedule configuration issue:</strong> <?= $escape($timezoneError) ?>
                </div>
            <?php endif; ?>
            <label class="flex items-start gap-3">
                <input class="mt-1" type="checkbox" name="schedule_enabled" value="1"
                    <?= $feed?->scheduleEnabled ? 'checked' : '' ?>>
                <span>
                    <span class="font-semibold">Enable scheduled updates</span><br>
                    <span class="text-sm text-secondary">Turn this off to pause the schedule without deleting it.</span>
                </span>
            </label>
            <div>
                <label class="form-label" for="podcast-schedule-timezone">Schedule timezone</label>
                <select class="form-input" id="podcast-schedule-timezone" name="schedule_timezone">
                    <?php if (!in_array($scheduleTimezone, $timezones, true)) : ?>
                        <option value="<?= $escape($scheduleTimezone) ?>" selected>
                            <?= $escape($scheduleTimezone) ?> (invalid or unavailable)
                        </option>
                    <?php endif; ?>
                    <?php foreach ($timezones as $timezone) : ?>
                        <option value="<?= $escape($timezone) ?>"
                            <?= $timezone === $scheduleTimezone ? 'selected' : '' ?>>
                            <?= $escape($timezone) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-2 text-sm text-secondary">
                    Stored as an IANA timezone. Daylight Saving Time changes are applied automatically.
                </p>
            </div>
            <div class="space-y-4">
                <?php foreach (PodcastScheduleDay::NAMES as $dayNumber => $dayName) : ?>
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2" style="min-width: 9rem">
                            <input type="checkbox" name="schedule_day_<?= $dayNumber ?>" value="1"
                                <?= array_key_exists($dayNumber, $scheduledDays) ? 'checked' : '' ?>>
                            <span><?= $escape($dayName) ?></span>
                        </label>
                        <label>
                            <span class="sr-only"><?= $escape($dayName) ?> update time</span>
                            <input class="form-input" type="time" name="schedule_time_<?= $dayNumber ?>"
                                value="<?= $escape($scheduledDays[$dayNumber] ?? '09:00') ?>">
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-sm text-secondary">
                Check a day to add it, change its time to edit it, or uncheck it to remove it.
                Times are displayed and evaluated in the timezone above.
            </p>
        </fieldset>

        <fieldset class="plugin-card space-y-4">
            <legend class="form-label">Feed options</legend>
            <label class="flex items-start gap-3">
                <input class="mt-1" type="checkbox" name="enabled" value="1"
                    <?= !$editing || $feed->enabled ? 'checked' : '' ?>>
                <span>
                    <span class="font-semibold">Public API output</span><br>
                    <span class="text-sm text-secondary">Include this feed and its episodes in API responses.</span>
                </span>
            </label>
        </fieldset>

        <div class="plugin-card text-sm text-secondary">
            Saving validates and refreshes the source immediately. If an edited feed cannot be refreshed,
            its most recent valid cached data is retained.
        </div>
        <div class="flex flex-wrap gap-3">
            <button class="button-primary" type="submit">Save and refresh</button>
            <a class="button-secondary" href="/cms/podcast">Cancel</a>
        </div>
    </form>
</section>
