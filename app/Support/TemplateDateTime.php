<?php

declare(strict_types=1);

namespace ReaCms\Support;

use DateTimeImmutable;
use DateTimeZone;

final class TemplateDateTime
{
    public static function local(?DateTimeImmutable $date, ?string $timezone = null): ?DateTimeImmutable
    {
        $timezone = trim($timezone ?? '');
        $valid = in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);

        return $date?->setTimezone(new DateTimeZone($valid ? $timezone : 'UTC'));
    }
}
