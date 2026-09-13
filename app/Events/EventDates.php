<?php

declare(strict_types=1);

namespace ReaCms\Events;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class EventDates
{
    public static function date(string $date): bool
    {
        $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        return $value !== false && $value->format('Y-m-d') === $date;
    }

    public static function local(string $date, string $time, string $timezone): DateTimeImmutable
    {
        if (!in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
            throw new InvalidArgumentException('Choose a valid IANA time zone, such as America/New_York.');
        }
        if (!self::date($date) || preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D', $time) !== 1) {
            throw new InvalidArgumentException('Enter valid dates and times.');
        }
        $time = strlen($time) === 5 ? $time . ':00' : $time;
        $local = $date . ' ' . $time;
        $value = new DateTimeImmutable($local, new DateTimeZone($timezone));
        if ($value->format('Y-m-d H:i:s') !== $local) {
            throw new InvalidArgumentException('This local time does not exist because the clocks change.');
        }
        // A repeated wall time has two possible instants. Require an unambiguous time rather than guessing.
        $transitions = $value->getTimezone()->getTransitions(
            $value->getTimestamp() - 86400,
            $value->getTimestamp() + 86400,
        );
        foreach ($transitions ?: [] as $transition) {
            $candidate = (new DateTimeImmutable($local, new DateTimeZone('UTC')))->getTimestamp()
                - $transition['offset'];
            if (
                $candidate !== $value->getTimestamp()
                && $value->setTimestamp($candidate)->format('Y-m-d H:i:s') === $local
            ) {
                throw new InvalidArgumentException(
                    'This local time occurs twice because the clocks change. Choose an unambiguous time.',
                );
            }
        }
        return $value;
    }

    public static function utc(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
