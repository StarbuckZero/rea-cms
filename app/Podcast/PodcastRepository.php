<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

use DateTimeImmutable;

interface PodcastRepository
{
    /** @return list<PodcastFeed> */
    public function feeds(bool $enabledOnly = false): array;

    public function feedById(int $id): ?PodcastFeed;

    public function feedBySlug(string $slug): ?PodcastFeed;

    /** @param list<PodcastScheduleDay> $scheduleDays */
    public function createFeed(
        string $slug,
        string $rssUrl,
        ?int $refreshIntervalMinutes,
        bool $automaticRefresh,
        string $refreshMode,
        bool $scheduleEnabled,
        string $scheduleTimezone,
        array $scheduleDays,
    ): PodcastFeed;

    /** @param list<PodcastScheduleDay> $scheduleDays */
    public function updateFeed(
        int $id,
        string $slug,
        string $rssUrl,
        bool $enabled,
        ?int $refreshIntervalMinutes,
        bool $automaticRefresh,
        string $refreshMode,
        bool $scheduleEnabled,
        string $scheduleTimezone,
        array $scheduleDays,
    ): void;

    public function deleteFeed(int $id): void;

    public function settings(): PodcastSettings;

    public function saveSettings(PodcastSettings $settings): void;

    /** @return list<PodcastEpisode> */
    public function episodes(?int $feedId, int $limit, int $offset): array;

    public function countEpisodes(?int $feedId): int;

    public function episode(int $feedId, string $episode): ?PodcastEpisode;

    public function acquireRefreshLock(int $feedId, DateTimeImmutable $now, int $seconds = 120): ?string;

    public function rescheduleFeed(int $feedId, DateTimeImmutable $nextRefreshAt): void;

    public function storeUpdatedFeed(
        PodcastFeed $feed,
        ParsedPodcast $podcast,
        FeedFetchResult $result,
        DateTimeImmutable $checkedAt,
        DateTimeImmutable $nextRefreshAt,
        string $lockToken,
    ): void;

    public function storeUnchangedFeed(
        PodcastFeed $feed,
        FeedFetchResult $result,
        DateTimeImmutable $checkedAt,
        DateTimeImmutable $nextRefreshAt,
        string $lockToken,
    ): void;

    public function storeRefreshFailure(
        int $feedId,
        string $message,
        ?int $httpStatus,
        DateTimeImmutable $checkedAt,
        DateTimeImmutable $nextRefreshAt,
        string $lockToken,
    ): void;
}
