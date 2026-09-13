<?php

declare(strict_types=1);

namespace ReaCms\Events;

use DateTimeImmutable;
use DateTimeZone;

final class EventPresenter
{
    /** @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public function present(array $row, bool $template = false): array
    {
        $zone = new DateTimeZone((string) $row['timezone']);
        $start = (new DateTimeImmutable((string) $row['start_utc']))->setTimezone($zone);
        $end = (new DateTimeImmutable((string) $row['end_utc']))->setTimezone($zone);
        $allDay = (bool) $row['all_day'];
        $lastDay = $allDay ? $end->modify('-1 day') : $end;
        $image = empty($row['public_image_id']) ? '' : '/media/' . $row['public_image_id'];
        $defaultImage = empty($row['default_image_id']) ? '' : '/media/' . $row['default_image_id'];
        $type = empty($row['type_name']) ? null : [
            'id' => (int) $row['type_id'], 'name' => (string) $row['type_name'], 'slug' => (string) $row['type_slug'],
        ];
        $location = ['name' => (string) $row['location']];
        foreach (['address', 'city', 'state', 'zip', 'country'] as $key) {
            $location[$key] = (string) $row[$key];
        }
        return [
            'id' => (int) $row['id'], 'title' => (string) $row['title'],
            'type' => $template ? ($type['name'] ?? '') : $type,
            'typeId' => $type['id'] ?? null, 'typeName' => $type['name'] ?? '', 'typeSlug' => $type['slug'] ?? '',
            'shortDescription' => (string) $row['short_description'], 'description' => (string) $row['description'],
            'image' => $image ?: $defaultImage, 'eventImage' => $image, 'defaultImage' => $defaultImage,
            'location' => $template ? $location['name'] : $location,
            'address' => $location['address'], 'city' => $location['city'], 'state' => $location['state'],
            'zip' => $location['zip'], 'country' => $location['country'],
            'startDate' => (string) $row['start_date'], 'endDate' => (string) $row['end_date'],
            'startTime' => $allDay ? null : $start->format('H:i:s'),
            'endTime' => $allDay ? null : $end->format('H:i:s'),
            'startDateTime' => $start->format(DATE_ATOM), 'endDateTime' => $end->format(DATE_ATOM),
            'timezone' => $row['timezone'], 'allDay' => $allDay,
            'formattedDate' => $start->format('F j, Y')
                . ($start->format('Y-m-d') === $lastDay->format('Y-m-d') ? '' : ' - ' . $lastDay->format('F j, Y')),
            'formattedTime' => $allDay ? 'All day' : $start->format('g:i A') . ' - ' . $end->format('g:i A'),
            'url' => (string) $row['url'], 'createdAt' => (string) $row['created_at'],
            'updatedAt' => (string) $row['updated_at'], 'published' => (bool) $row['published'],
            'calendarUrl' => '/api/v1/events/' . $row['id'] . '.ics',
        ];
    }
}
