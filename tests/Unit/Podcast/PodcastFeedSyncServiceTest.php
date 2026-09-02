<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Podcast;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Podcast\FeedFetcher;
use ReaCms\Podcast\FeedFetchResult;
use ReaCms\Podcast\PodcastException;
use ReaCms\Podcast\PodcastFeed;
use ReaCms\Podcast\PodcastFeedParser;
use ReaCms\Podcast\PodcastFeedSyncService;
use ReaCms\Podcast\PodcastSettings;
use ReaCms\Podcast\PodcastSchedule;
use ReaCms\Podcast\PodcastScheduleDay;
use ReaCms\Tests\Support\FrozenClock;
use ReaCms\Tests\Support\InMemoryPodcastRepository;

final class PodcastFeedSyncServiceTest extends TestCase
{
    public function testFreshCacheDoesNotFetchAndExpiredCacheDoes(): void
    {
        $repository = new InMemoryPodcastRepository();
        $now = new DateTimeImmutable('2026-08-31T12:00:00+00:00');
        $fresh = $this->feed(nextRefreshAt: $now->modify('+1 minute'));
        $repository->records[1] = $fresh;
        $fetcher = new class implements FeedFetcher {
            public int $calls = 0;
            public function fetch(PodcastFeed $feed, PodcastSettings $settings): FeedFetchResult
            {
                $this->calls++;
                return new FeedFetchResult(304);
            }
        };
        $sync = new PodcastFeedSyncService($repository, $fetcher, new PodcastFeedParser(), new FrozenClock($now));

        self::assertFalse($sync->refreshIfDue($fresh));
        self::assertSame(0, $fetcher->calls);

        $expired = $this->feed(nextRefreshAt: $now);
        self::assertTrue($sync->refreshIfDue($expired));
        self::assertSame(1, $fetcher->calls);
        self::assertSame(1, $repository->unchanged);
    }

    public function testRefreshFailurePreservesCacheStateAndReleasesLock(): void
    {
        $repository = new InMemoryPodcastRepository();
        $fetcher = new class implements FeedFetcher {
            public function fetch(PodcastFeed $feed, PodcastSettings $settings): FeedFetchResult
            {
                throw new PodcastException('Temporary outage');
            }
        };
        $sync = new PodcastFeedSyncService(
            $repository,
            $fetcher,
            new PodcastFeedParser(),
            new FrozenClock(new DateTimeImmutable('2026-08-31T12:00:00+00:00')),
        );

        self::assertFalse($sync->refreshIfDue($this->feed()));
        self::assertSame(1, $repository->failed);
        self::assertSame('Temporary outage', $repository->lastError);
        self::assertFalse($repository->locked);
        self::assertSame(0, $repository->updated);
    }

    public function testConcurrentRefreshCannotAcquireSecondFeedLock(): void
    {
        $repository = new InMemoryPodcastRepository();
        $repository->locked = true;
        $fetcher = new class implements FeedFetcher {
            public int $calls = 0;
            public function fetch(PodcastFeed $feed, PodcastSettings $settings): FeedFetchResult
            {
                $this->calls++;
                return new FeedFetchResult(304);
            }
        };
        $sync = new PodcastFeedSyncService(
            $repository,
            $fetcher,
            new PodcastFeedParser(),
            new FrozenClock(new DateTimeImmutable('2026-08-31T12:00:00+00:00')),
        );

        self::assertFalse($sync->forceRefresh($this->feed()));
        self::assertSame(0, $fetcher->calls);
    }

    public function testInitialRefreshCannotAcceptNotModifiedWithoutCachedData(): void
    {
        $repository = new InMemoryPodcastRepository();
        $fetcher = new class implements FeedFetcher {
            public function fetch(PodcastFeed $feed, PodcastSettings $settings): FeedFetchResult
            {
                return new FeedFetchResult(304);
            }
        };
        $sync = new PodcastFeedSyncService(
            $repository,
            $fetcher,
            new PodcastFeedParser(),
            new FrozenClock(new DateTimeImmutable('2026-08-31T12:00:00+00:00')),
        );

        self::assertFalse($sync->refreshIfDue($this->feed(cached: false)));
        self::assertSame(1, $repository->failed);
    }

    public function testOverdueScheduleDoesNotRunOnAnUnselectedLocalDay(): void
    {
        $repository = new InMemoryPodcastRepository();
        $now = new DateTimeImmutable('2026-09-01T14:00:00+00:00'); // Tuesday in New York.
        $feed = $this->scheduledFeed(
            [new PodcastScheduleDay(1, '09:00')],
            new DateTimeImmutable('2026-08-31T13:00:00+00:00'),
        );
        $fetcher = new class implements FeedFetcher {
            public int $calls = 0;
            public function fetch(PodcastFeed $feed, PodcastSettings $settings): FeedFetchResult
            {
                $this->calls++;
                return new FeedFetchResult(304);
            }
        };
        $sync = new PodcastFeedSyncService($repository, $fetcher, new PodcastFeedParser(), new FrozenClock($now));

        self::assertFalse($sync->refreshIfDue($feed));
        self::assertSame(0, $fetcher->calls);
        self::assertSame('2026-09-07T13:00:00+00:00', $repository->rescheduledAt?->format(DATE_ATOM));
    }

    public function testDueScheduleRunsOnSelectedLocalDay(): void
    {
        $repository = new InMemoryPodcastRepository();
        $now = new DateTimeImmutable('2026-08-31T13:05:00+00:00');
        $feed = $this->scheduledFeed(
            [new PodcastScheduleDay(1, '09:00')],
            new DateTimeImmutable('2026-08-31T13:00:00+00:00'),
        );
        $fetcher = new class implements FeedFetcher {
            public function fetch(PodcastFeed $feed, PodcastSettings $settings): FeedFetchResult
            {
                return new FeedFetchResult(304);
            }
        };
        $sync = new PodcastFeedSyncService($repository, $fetcher, new PodcastFeedParser(), new FrozenClock($now));

        self::assertTrue($sync->refreshIfDue($feed));
        self::assertSame(1, $repository->unchanged);
    }

    /** @param list<PodcastScheduleDay> $days */
    private function scheduledFeed(array $days, DateTimeImmutable $nextRefreshAt): PodcastFeed
    {
        return new PodcastFeed(
            id: 1,
            slug: 'example',
            rssUrl: 'https://example.com/feed.xml',
            enabled: true,
            refreshIntervalMinutes: null,
            automaticRefresh: true,
            nextRefreshAt: $nextRefreshAt,
            lastSuccessfulRefreshAt: new DateTimeImmutable('2026-08-31T11:00:00+00:00'),
            contentHash: str_repeat('a', 64),
            refreshMode: PodcastSchedule::MODE_SCHEDULE,
            scheduleEnabled: true,
            scheduleTimezone: 'America/New_York',
            scheduleDays: $days,
        );
    }

    private function feed(?DateTimeImmutable $nextRefreshAt = null, bool $cached = true): PodcastFeed
    {
        return new PodcastFeed(
            id: 1,
            slug: 'example',
            rssUrl: 'https://example.com/feed.xml',
            enabled: true,
            refreshIntervalMinutes: null,
            automaticRefresh: true,
            nextRefreshAt: $nextRefreshAt,
            lastSuccessfulRefreshAt: $cached ? new DateTimeImmutable('2026-08-31T11:00:00+00:00') : null,
            contentHash: $cached ? str_repeat('a', 64) : null,
        );
    }
}
