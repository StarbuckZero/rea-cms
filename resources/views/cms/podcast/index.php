<?php

declare(strict_types=1);

use ReaCms\Podcast\PodcastFeed;
use ReaCms\Podcast\PodcastSettings;
use ReaCms\Podcast\PodcastSchedule;

/** @var callable(mixed): string $escape */
/** @var list<PodcastFeed> $feeds */
/** @var PodcastSettings $settings */
/** @var string $csrfToken */
/** @var string $message */
/** @var PodcastSchedule $schedule */
?>
<section>
    <p class="eyebrow">Content</p>
    <div class="widget-heading mt-3">
        <div>
            <h1 class="text-3xl font-bold">Podcast Feeds</h1>
            <p class="mt-2 text-secondary">RSS is synchronized into the database and served from cached data.</p>
        </div>
        <a class="button-primary" href="/cms/podcast/new">Add feed</a>
    </div>
    <?php if ($message !== '') : ?>
        <div class="panel mt-6"><p><?= $escape($message) ?></p></div>
    <?php endif; ?>
    <div class="panel mt-8">
        <table class="cms-table">
            <thead>
                <tr>
                    <th>Feed</th>
                    <th>Refresh state</th>
                    <th>Timing</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feeds as $feed) : ?>
                    <tr>
                        <td>
                            <strong><?= $escape($feed->title !== '' ? $feed->title : $feed->slug) ?></strong><br>
                            <span class="text-sm text-secondary">/api/v1/podcast/<?= $escape($feed->slug) ?>.json</span><br>
                            <span class="text-sm text-secondary"><?= $feed->enabled ? 'Enabled' : 'Disabled' ?></span>
                        </td>
                        <td>
                            <?php $scheduleError = $schedule->configurationError($feed); ?>
                            <?= $escape($feed->refreshStatus) ?>
                            <?php if ($feed->lastHttpStatus !== null) : ?>
                                (HTTP <?= (int) $feed->lastHttpStatus ?>)
                            <?php endif; ?>
                            <?php if ($feed->lastError !== null) : ?>
                                <br><span class="text-sm text-secondary"><?= $escape($feed->lastError) ?></span>
                            <?php endif; ?>
                            <?php if ($scheduleError !== null) : ?>
                                <br><span class="text-sm text-secondary">Configuration issue: <?= $escape($scheduleError) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($feed->refreshMode === PodcastSchedule::MODE_SCHEDULE) : ?>
                                <span class="text-sm">Weekly schedule: <?= $feed->scheduleEnabled ? 'Enabled' : 'Paused' ?></span><br>
                                <span class="text-sm">Timezone: <?= $escape($feed->scheduleTimezone) ?></span><br>
                                <span class="text-sm">Days:
                                    <?= $escape(implode(', ', array_map(
                                        static fn ($day): string => $day->name() . ' ' . $day->localTime,
                                        $feed->scheduleDays,
                                    )) ?: 'None') ?>
                                </span><br>
                            <?php else : ?>
                                <span class="text-sm">Interval: <?= (int) $settings->intervalFor($feed) ?> minutes</span><br>
                            <?php endif; ?>
                            <?php if ($schedule->validTimezone($feed->scheduleTimezone)) : ?>
                                <?php $displayTimezone = new DateTimeZone($feed->scheduleTimezone); ?>
                                <span class="text-sm">Checked: <?= $escape($feed->lastCheckedAt?->setTimezone($displayTimezone)->format(DATE_ATOM) ?? 'Never') ?></span><br>
                                <span class="text-sm">Updated: <?= $escape($feed->lastChangedAt?->setTimezone($displayTimezone)->format(DATE_ATOM) ?? 'Never') ?></span><br>
                                <span class="text-sm">Next:
                                    <?php if ($feed->refreshMode === PodcastSchedule::MODE_SCHEDULE && !$feed->scheduleEnabled) : ?>
                                        Paused
                                    <?php else : ?>
                                        <?= $escape($feed->nextRefreshAt?->setTimezone($displayTimezone)->format(DATE_ATOM) ?? 'Not scheduled') ?>
                                    <?php endif; ?>
                                </span>
                            <?php else : ?>
                                <span class="text-sm">Schedule times unavailable until the timezone is corrected.</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/cms/podcast/<?= (int) $feed->id ?>/edit">Edit</a>
                            <form method="post" action="/cms/podcast/<?= (int) $feed->id ?>/refresh" class="mt-2">
                                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                <button type="submit">Refresh</button>
                            </form>
                            <form method="post" action="/cms/podcast/<?= (int) $feed->id ?>/delete" class="mt-2">
                                <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
                                <button type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($feeds === []) : ?>
                    <tr><td colspan="4">No podcast feeds have been configured.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="panel mt-8">
        <h2 class="text-xl font-bold">Refresh settings</h2>
        <form method="post" action="/cms/podcast/settings" class="mt-6">
            <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
            <label>
                Default refresh interval (minutes)
                <input type="number" min="1" max="1440" name="default_refresh_interval"
                    value="<?= (int) $settings->defaultRefreshIntervalMinutes ?>">
            </label>
            <label class="mt-4">
                <input type="checkbox" name="automatic_refresh" value="1"
                    <?= $settings->automaticRefresh ? 'checked' : '' ?>>
                Enable automatic refresh
            </label>
            <label class="mt-4">
                Request timeout (seconds)
                <input type="number" min="1" max="60" name="request_timeout"
                    value="<?= (int) $settings->requestTimeoutSeconds ?>">
            </label>
            <label class="mt-4">
                Maximum RSS download size (bytes)
                <input type="number" min="65536" max="52428800" name="maximum_download_size"
                    value="<?= (int) $settings->maximumDownloadBytes ?>">
            </label>
            <div class="mt-6"><button class="button-primary" type="submit">Save settings</button></div>
        </form>
    </div>
</section>
