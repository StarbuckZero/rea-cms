<?php

declare(strict_types=1);

use ReaCms\Podcast\PodcastFeed;
use ReaCms\Podcast\PodcastSettings;

/** @var callable(mixed): string $escape */
/** @var list<PodcastFeed> $feeds */
/** @var PodcastSettings $settings */
/** @var string $csrfToken */
/** @var string $message */
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
                            <?= $escape($feed->refreshStatus) ?>
                            <?php if ($feed->lastHttpStatus !== null) : ?>
                                (HTTP <?= (int) $feed->lastHttpStatus ?>)
                            <?php endif; ?>
                            <?php if ($feed->lastError !== null) : ?>
                                <br><span class="text-sm text-secondary"><?= $escape($feed->lastError) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="text-sm">Checked: <?= $escape($feed->lastCheckedAt?->format(DATE_ATOM) ?? 'Never') ?></span><br>
                            <span class="text-sm">Updated: <?= $escape($feed->lastChangedAt?->format(DATE_ATOM) ?? 'Never') ?></span><br>
                            <span class="text-sm">Next: <?= $escape($feed->nextRefreshAt?->format(DATE_ATOM) ?? 'On request') ?></span>
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
