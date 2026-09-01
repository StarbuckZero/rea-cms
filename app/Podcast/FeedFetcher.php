<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

interface FeedFetcher
{
    public function fetch(PodcastFeed $feed, PodcastSettings $settings): FeedFetchResult;
}
