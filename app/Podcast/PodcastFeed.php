<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

use DateTimeImmutable;

final class PodcastFeed
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $rssUrl,
        public readonly bool $enabled,
        public readonly ?int $refreshIntervalMinutes,
        public readonly bool $automaticRefresh,
        public readonly string $title = '',
        public readonly string $description = '',
        public readonly string $link = '',
        public readonly string $language = '',
        public readonly string $author = '',
        public readonly string $imageUrl = '',
        public readonly bool $explicit = false,
        public readonly ?DateTimeImmutable $lastCheckedAt = null,
        public readonly ?DateTimeImmutable $lastSuccessfulRefreshAt = null,
        public readonly ?DateTimeImmutable $lastChangedAt = null,
        public readonly ?DateTimeImmutable $nextRefreshAt = null,
        public readonly ?string $etag = null,
        public readonly ?string $lastModified = null,
        public readonly ?string $lastError = null,
        public readonly ?int $lastHttpStatus = null,
        public readonly string $refreshStatus = 'current',
        public readonly ?string $contentHash = null,
        public readonly string $refreshMode = PodcastSchedule::MODE_INTERVAL,
        public readonly bool $scheduleEnabled = false,
        public readonly string $scheduleTimezone = PodcastSchedule::APPLICATION_DEFAULT_TIMEZONE,
        /** @var list<PodcastScheduleDay> */
        public readonly array $scheduleDays = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function api(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'link' => $this->link,
            'language' => $this->language,
            'author' => $this->author,
            'imageUrl' => $this->imageUrl,
            'explicit' => $this->explicit,
        ];
    }
}
