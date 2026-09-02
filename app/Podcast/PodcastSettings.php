<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

final class PodcastSettings
{
    public function __construct(
        public readonly int $defaultRefreshIntervalMinutes = 30,
        public readonly bool $automaticRefresh = true,
        public readonly int $requestTimeoutSeconds = 10,
        public readonly int $maximumDownloadBytes = 5_242_880,
    ) {
    }

    public function intervalFor(PodcastFeed $feed): int
    {
        return max(1, $feed->refreshIntervalMinutes ?? $this->defaultRefreshIntervalMinutes);
    }
}
