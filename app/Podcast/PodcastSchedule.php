<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

use DateTimeImmutable;
use DateTimeZone;

final class PodcastSchedule
{
    public const MODE_INTERVAL = 'interval';
    public const MODE_SCHEDULE = 'schedule';
    public const APPLICATION_DEFAULT_TIMEZONE = 'UTC';

    /** @var array<string, true>|null */
    private static ?array $timezones = null;

    public function validTimezone(string $timezone): bool
    {
        if (self::$timezones === null) {
            self::$timezones = array_fill_keys(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);
            self::$timezones['UTC'] = true;
        }
        return isset(self::$timezones[$timezone]);
    }

    /** @return list<string> */
    public function timezoneIdentifiers(): array
    {
        $identifiers = DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC);
        if (!in_array('UTC', $identifiers, true)) {
            array_unshift($identifiers, 'UTC');
        }
        return $identifiers;
    }

    public function defaultTimezone(?string $configured): string
    {
        $timezone = trim($configured ?? '');
        return $this->validTimezone($timezone) ? $timezone : self::APPLICATION_DEFAULT_TIMEZONE;
    }

    public function configurationError(PodcastFeed $feed): ?string
    {
        if ($feed->refreshMode !== self::MODE_SCHEDULE) {
            return null;
        }
        if (!$this->validTimezone($feed->scheduleTimezone)) {
            return 'The saved schedule timezone is unavailable or is not a valid IANA timezone identifier.';
        }
        if ($feed->scheduleEnabled && $feed->scheduleDays === []) {
            return 'Scheduled updates are enabled, but no days have been selected.';
        }
        return null;
    }

    public function nextRun(PodcastFeed $feed, DateTimeImmutable $after): DateTimeImmutable
    {
        $this->assertRunnable($feed);
        $zone = new DateTimeZone($feed->scheduleTimezone);
        $localAfter = $after->setTimezone($zone);
        $next = null;
        for ($offset = 0; $offset <= 7; $offset++) {
            $date = $localAfter->modify('+' . $offset . ' days');
            foreach ($feed->scheduleDays as $scheduledDay) {
                if ($scheduledDay->dayOfWeek !== (int) $date->format('w')) {
                    continue;
                }
                $candidate = $this->localOccurrence($date, $scheduledDay->localTime, $zone);
                if ($candidate <= $after || ($next !== null && $candidate >= $next)) {
                    continue;
                }
                $next = $candidate;
            }
        }
        if ($next === null) {
            throw new PodcastException('The next scheduled RSS update could not be calculated.');
        }
        return $next->setTimezone(new DateTimeZone('UTC'));
    }

    public function shouldRunNow(PodcastFeed $feed, DateTimeImmutable $now): bool
    {
        $this->assertRunnable($feed);
        $zone = new DateTimeZone($feed->scheduleTimezone);
        $localNow = $now->setTimezone($zone);
        foreach ($feed->scheduleDays as $scheduledDay) {
            if ($scheduledDay->dayOfWeek !== (int) $localNow->format('w')) {
                continue;
            }
            return $this->localOccurrence($localNow, $scheduledDay->localTime, $zone) <= $now;
        }
        return false;
    }

    private function assertRunnable(PodcastFeed $feed): void
    {
        $error = $this->configurationError($feed);
        if ($error !== null) {
            throw new PodcastException($error);
        }
        if ($feed->scheduleDays === []) {
            throw new PodcastException('At least one scheduled day is required.');
        }
    }

    private function localOccurrence(
        DateTimeImmutable $date,
        string $localTime,
        DateTimeZone $zone,
    ): DateTimeImmutable {
        $candidate = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $localTime . ':00',
            $zone,
        );
        if ($candidate === false) {
            throw new PodcastException('A scheduled RSS update time is invalid.');
        }
        // PHP advances nonexistent spring-forward wall times to the next real local instant.
        // Ambiguous fall-back wall times resolve to one instant, preventing a duplicate run.
        return $candidate;
    }
}
