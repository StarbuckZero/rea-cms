<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Podcast;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Podcast\PodcastEpisode;

final class PodcastEpisodeTest extends TestCase
{
    public function testDurationFormattingPreservesSecondsAndHandlesBoundaries(): void
    {
        foreach (
            [
            [null, ''], [-1, ''], [0, '0 minutes'], [59, '0 minutes'],
            [60, '1 minute'], [3599, '59 minutes'], [3600, '1 hour 0 minutes'],
            [3660, '1 hour 1 minute'], [5100, '1 hour 25 minutes'], [7320, '2 hours 2 minutes'],
            ] as [$seconds, $expected]
        ) {
            $data = $this->episode($seconds)->api();
            self::assertSame($expected, $data['audio']['durationFormatted']);
            self::assertSame($seconds, $data['audio']['durationSeconds']);
            self::assertSame('', $data['publishedDate']);
            self::assertSame('', $data['publishedTime']);
        }
    }

    public function testPublicationFormattingUsesTimezoneAndSafeDefaults(): void
    {
        $episode = $this->episode(60, new DateTimeImmutable('2026-09-09T00:30:00Z'));
        foreach (['America/New_York', null, '', 'Invalid/Zone'] as $zone) {
            $data = $episode->api($zone, 'America/New_York');
            self::assertSame('September 8, 2026', $data['publishedDate']);
            self::assertSame('8:30 PM', $data['publishedTime']);
            self::assertSame('2026-09-09T00:30:00+00:00', $data['publishedAt']);
        }
        self::assertSame('12:30 AM', $episode->api('Invalid/Zone', 'invalid')['publishedTime']);
        self::assertSame('7:30 PM', $this->episode(60, new DateTimeImmutable('2026-01-09T00:30:00Z'))
            ->api('America/New_York')['publishedTime']);
    }

    private function episode(?int $seconds, ?DateTimeImmutable $published = null): PodcastEpisode
    {
        return new PodcastEpisode(
            1,
            1,
            'show',
            'Show',
            'guid',
            'episode',
            'Episode',
            '',
            '',
            '',
            '',
            null,
            '',
            $seconds,
            '',
            false,
            'full',
            $published,
        );
    }
}
