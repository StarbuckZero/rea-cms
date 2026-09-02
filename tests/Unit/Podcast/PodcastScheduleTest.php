<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Podcast;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Podcast\PodcastFeed;
use ReaCms\Podcast\PodcastSchedule;
use ReaCms\Podcast\PodcastScheduleDay;

final class PodcastScheduleTest extends TestCase
{
    public function testDefaultIntervalIsThirtyMinutes(): void
    {
        self::assertSame(30, (new \ReaCms\Podcast\PodcastSettings())->defaultRefreshIntervalMinutes);
    }

    public function testWeekdayIsCalculatedInSelectedTimezoneAcrossUtcDateBoundary(): void
    {
        $schedule = new PodcastSchedule();
        $feed = $this->feed('Asia/Tokyo', [new PodcastScheduleDay(1, '00:30')]);

        $next = $schedule->nextRun($feed, new DateTimeImmutable('2026-08-30T14:00:00+00:00'));

        self::assertSame('2026-08-30T15:30:00+00:00', $next->format(DATE_ATOM));
        self::assertTrue($schedule->shouldRunNow(
            $feed,
            new DateTimeImmutable('2026-08-30T15:31:00+00:00'),
        ));
    }

    public function testSpringForwardTimeAdvancesToARealInstantInsteadOfBeingSkipped(): void
    {
        $schedule = new PodcastSchedule();
        $feed = $this->feed('America/New_York', [new PodcastScheduleDay(0, '02:30')]);

        $next = $schedule->nextRun($feed, new DateTimeImmutable('2026-03-08T06:00:00+00:00'));

        self::assertSame('2026-03-08T07:30:00+00:00', $next->format(DATE_ATOM));
        self::assertTrue($schedule->shouldRunNow(
            $feed,
            new DateTimeImmutable('2026-03-08T07:31:00+00:00'),
        ));
    }

    public function testFallBackOccurrenceSchedulesOnlyOnce(): void
    {
        $schedule = new PodcastSchedule();
        $feed = $this->feed('America/New_York', [new PodcastScheduleDay(0, '01:30')]);
        $first = $schedule->nextRun($feed, new DateTimeImmutable('2026-11-01T04:00:00+00:00'));

        $following = $schedule->nextRun($feed, $first);

        self::assertGreaterThan(6 * 24 * 60 * 60, $following->getTimestamp() - $first->getTimestamp());
        self::assertSame('2026-11-08', $following->setTimezone(
            new \DateTimeZone('America/New_York'),
        )->format('Y-m-d'));
    }

    public function testInvalidSavedTimezoneIsReported(): void
    {
        $feed = $this->feed('UTC-4', [new PodcastScheduleDay(1, '09:00')]);

        self::assertNotNull((new PodcastSchedule())->configurationError($feed));
    }

    /** @param list<PodcastScheduleDay> $days */
    private function feed(string $timezone, array $days): PodcastFeed
    {
        return new PodcastFeed(
            id: 1,
            slug: 'example',
            rssUrl: 'https://example.com/feed.xml',
            enabled: true,
            refreshIntervalMinutes: null,
            automaticRefresh: true,
            refreshMode: PodcastSchedule::MODE_SCHEDULE,
            scheduleEnabled: true,
            scheduleTimezone: $timezone,
            scheduleDays: $days,
        );
    }
}
