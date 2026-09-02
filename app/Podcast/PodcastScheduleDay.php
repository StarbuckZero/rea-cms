<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

final class PodcastScheduleDay
{
    public const NAMES = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public function __construct(
        public readonly int $dayOfWeek,
        public readonly string $localTime,
    ) {
        if (!array_key_exists($dayOfWeek, self::NAMES) || !$this->validTime($localTime)) {
            throw new PodcastException('Each scheduled day must have a valid weekday and local time.');
        }
    }

    public function name(): string
    {
        return self::NAMES[$this->dayOfWeek];
    }

    private function validTime(string $time): bool
    {
        return preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time) === 1;
    }
}
