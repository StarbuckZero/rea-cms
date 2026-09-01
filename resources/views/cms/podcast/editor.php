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
    <form class="panel mt-8" method="post" action="<?= $editing ? '/cms/podcast/' . (int) $feed->id : '/cms/podcast' ?>">
        <input type="hidden" name="_csrf" value="<?= $escape($csrfToken) ?>">
        <label>
            Slug
            <input required name="slug" value="<?= $escape($feed?->slug ?? '') ?>" placeholder="my-podcast">
        </label>
        <label class="mt-4">
            RSS feed URL
            <input required type="url" name="rss_url" value="<?= $escape($feed?->rssUrl ?? '') ?>"
                placeholder="https://example.com/podcast.xml">
        </label>
        <label class="mt-4">
            Refresh interval override (minutes)
            <input type="number" min="1" max="1440" name="refresh_interval"
                value="<?= $escape($feed?->refreshIntervalMinutes ?? '') ?>"
                placeholder="Use global default">
        </label>
        <label class="mt-4">
            <input type="checkbox" name="automatic_refresh" value="1"
                <?= !$editing || $feed->automaticRefresh ? 'checked' : '' ?>>
            Automatically refresh this feed
        </label>
        <label class="mt-4">
            <input type="checkbox" name="enabled" value="1" <?= !$editing || $feed->enabled ? 'checked' : '' ?>>
            Enable public API output
        </label>
        <p class="mt-4 text-sm text-secondary">
            Saving validates and refreshes the source immediately. Existing cached data is retained if an edited feed fails.
        </p>
        <div class="mt-6">
            <button class="button-primary" type="submit">Save and refresh</button>
            <a href="/cms/podcast">Cancel</a>
        </div>
    </form>
</section>
