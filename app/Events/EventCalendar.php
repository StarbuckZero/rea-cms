<?php

declare(strict_types=1);

namespace ReaCms\Events;

use DateTimeImmutable;
use DateTimeZone;
use ReaCms\Support\PlainText;

final class EventCalendar
{
    /** @param array<string,mixed> $event */
    public function render(array $event, string $siteUrl): string
    {
        $start = new DateTimeImmutable((string) $event['startDateTime']);
        $end = new DateTimeImmutable((string) $event['endDateTime']);
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//REA CMS//Events//EN', 'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT', 'UID:events-' . $event['id'] . '-' . hash('sha256', $siteUrl) . '@rea-cms',
            'DTSTAMP:' . $this->utc((string) $event['updatedAt']),
            'CREATED:' . $this->utc((string) $event['createdAt']),
            'LAST-MODIFIED:' . $this->utc((string) $event['updatedAt'])];
        if ($event['allDay']) {
            $lines[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $end->format('Ymd');
        } else {
            // UTC instants avoid floating times and incomplete VTIMEZONE definitions across calendar clients.
            $lines[] = 'DTSTART:' . $this->utc((string) $event['startDateTime']);
            $lines[] = 'DTEND:' . $this->utc((string) $event['endDateTime']);
        }
        $lines[] = 'X-REA-TIMEZONE:' . $this->text((string) $event['timezone']);
        $lines[] = 'SUMMARY:' . $this->text((string) $event['title']);
        $lines[] = 'DESCRIPTION:' . $this->text((string) ($event['description'] ?: $event['shortDescription']));
        $location = is_array($event['location']) ? $event['location'] : [];
        $parts = array_filter($location, static fn (mixed $value): bool => is_string($value) && $value !== '');
        $lines[] = 'LOCATION:' . $this->text(implode(', ', $parts));
        if ($event['url'] !== '') {
            $lines[] = 'URL:' . str_replace(["\r", "\n"], '', (string) $event['url']);
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", array_map($this->fold(...), $lines)) . "\r\n";
    }

    private function utc(string $date): string
    {
        return (new DateTimeImmutable($date))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    private function text(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], PlainText::fromHtml($value, false));
    }

    private function fold(string $line): string
    {
        $parts = [];
        $limit = 75;
        while (strlen($line) > $limit) {
            $part = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $part;
            $line = substr($line, strlen($part));
            $limit = 74;
        }
        $parts[] = $line;
        return implode("\r\n ", $parts);
    }
}
