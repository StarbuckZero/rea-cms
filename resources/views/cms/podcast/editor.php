<?php

declare(strict_types=1);

use ReaCms\Podcast\PodcastFeed;

/** @var callable(mixed): string $escape */
/** @var PodcastFeed|null $feed */
/** @var string $csrfToken */
$editing = $feed !== null;
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

        <div>
            <label class="form-label" for="podcast-refresh-interval">Refresh interval override</label>
            <input class="form-input" id="podcast-refresh-interval" type="number" min="1" max="1440"
                   name="refresh_interval" value="<?= $escape($feed?->refreshIntervalMinutes ?? '') ?>"
                   placeholder="Use global default" aria-describedby="podcast-refresh-help">
            <p class="mt-2 text-sm text-secondary" id="podcast-refresh-help">
                Optional, in minutes. Leave blank to use the global Podcast Feed setting.
            </p>
        </div>

        <fieldset class="plugin-card space-y-4">
            <legend class="form-label">Feed options</legend>
            <label class="flex items-start gap-3">
                <input class="mt-1" type="checkbox" name="automatic_refresh" value="1"
                    <?= !$editing || $feed->automaticRefresh ? 'checked' : '' ?>>
                <span>
                    <span class="font-semibold">Automatic refresh</span><br>
                    <span class="text-sm text-secondary">Check this feed when its refresh interval expires.</span>
                </span>
            </label>
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
