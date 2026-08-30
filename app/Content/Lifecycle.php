<?php

declare(strict_types=1);

namespace ReaCms\Content;

use DateTimeImmutable;
use DateTimeZone;

final class Lifecycle
{
    public const STATES = ['draft', 'pending', 'scheduled', 'published', 'archived', 'trashed'];

    public function transition(string $from, string $to): void
    {
        $allowed = [
            'draft' => ['pending', 'scheduled', 'published', 'trashed'],
            'pending' => ['draft', 'scheduled', 'published', 'trashed'],
            'scheduled' => ['draft', 'published', 'trashed'],
            'published' => ['draft', 'archived', 'trashed'],
            'archived' => ['draft', 'trashed'],
            'trashed' => ['draft'],
        ];
        if (!in_array($to, $allowed[$from] ?? [], true)) {
            throw new ContentException('The content lifecycle transition is not allowed.');
        }
    }

    public function scheduleUtc(string $localDateTime, string $timezone): DateTimeImmutable
    {
        $zone = new DateTimeZone($timezone);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $localDateTime, $zone);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new ContentException('The scheduled local date and timezone are invalid.');
        }
        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    public function publiclyVisible(string $state, ?DateTimeImmutable $publishAt, DateTimeImmutable $now): bool
    {
        return $state === 'published' && ($publishAt === null || $publishAt <= $now);
    }
}
