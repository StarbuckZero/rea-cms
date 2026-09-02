<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use DateTimeImmutable;
use ReaCms\Podcast\FeedFetchResult;
use ReaCms\Podcast\ParsedPodcast;
use ReaCms\Podcast\PodcastEpisode;
use ReaCms\Podcast\PodcastFeed;
use ReaCms\Podcast\PodcastRepository;
use ReaCms\Podcast\PodcastSettings;

final class InMemoryPodcastRepository implements PodcastRepository
{
    /** @var array<int, PodcastFeed> */
    public array $records = [];
    public PodcastSettings $configuration;
    public bool $locked = false;
    public int $updated = 0;
    public int $unchanged = 0;
    public int $failed = 0;
    public ?string $lastError = null;
    public ?DateTimeImmutable $rescheduledAt = null;

    public function __construct()
    {
        $this->configuration = new PodcastSettings();
    }

    public function feeds(bool $enabledOnly = false): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (PodcastFeed $feed): bool => !$enabledOnly || $feed->enabled,
        ));
    }

    public function feedById(int $id): ?PodcastFeed
    {
        return $this->records[$id] ?? null;
    }

    public function feedBySlug(string $slug): ?PodcastFeed
    {
        foreach ($this->records as $feed) {
            if ($feed->slug === $slug) {
                return $feed;
            }
        }
        return null;
    }

    /** @param list<\ReaCms\Podcast\PodcastScheduleDay> $scheduleDays */
    public function createFeed(
        string $slug,
        string $rssUrl,
        ?int $refreshIntervalMinutes,
        bool $automaticRefresh,
        string $refreshMode,
        bool $scheduleEnabled,
        string $scheduleTimezone,
        array $scheduleDays,
    ): PodcastFeed {
        $feed = new PodcastFeed(
            count($this->records) + 1,
            $slug,
            $rssUrl,
            true,
            $refreshIntervalMinutes,
            $automaticRefresh,
            refreshMode: $refreshMode,
            scheduleEnabled: $scheduleEnabled,
            scheduleTimezone: $scheduleTimezone,
            scheduleDays: $scheduleDays,
        );
        $this->records[$feed->id] = $feed;
        return $feed;
    }

    /** @param list<\ReaCms\Podcast\PodcastScheduleDay> $scheduleDays */
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
    ): void {
    }

    public function deleteFeed(int $id): void
    {
        unset($this->records[$id]);
    }

    public function settings(): PodcastSettings
    {
        return $this->configuration;
    }

    public function saveSettings(PodcastSettings $settings): void
    {
        $this->configuration = $settings;
    }

    public function episodes(?int $feedId, int $limit, int $offset): array
    {
        return [];
    }

    public function countEpisodes(?int $feedId): int
    {
        return 0;
    }

    public function episode(int $feedId, string $episode): ?PodcastEpisode
    {
        return null;
    }

    public function acquireRefreshLock(int $feedId, DateTimeImmutable $now, int $seconds = 120): ?string
    {
        if ($this->locked) {
            return null;
        }
        $this->locked = true;
        return 'lock';
    }

    public function rescheduleFeed(int $feedId, DateTimeImmutable $nextRefreshAt): void
    {
        $this->rescheduledAt = $nextRefreshAt;
    }

    public function storeUpdatedFeed(
        PodcastFeed $feed,
        ParsedPodcast $podcast,
        FeedFetchResult $result,
        DateTimeImmutable $checkedAt,
        DateTimeImmutable $nextRefreshAt,
        string $lockToken,
    ): void {
        $this->updated++;
        $this->locked = false;
    }

    public function storeUnchangedFeed(
        PodcastFeed $feed,
        FeedFetchResult $result,
        DateTimeImmutable $checkedAt,
        DateTimeImmutable $nextRefreshAt,
        string $lockToken,
    ): void {
        $this->unchanged++;
        $this->locked = false;
    }

    public function storeRefreshFailure(
        int $feedId,
        string $message,
        ?int $httpStatus,
        DateTimeImmutable $checkedAt,
        DateTimeImmutable $nextRefreshAt,
        string $lockToken,
    ): void {
        $this->failed++;
        $this->lastError = $message;
        $this->locked = false;
    }
}
