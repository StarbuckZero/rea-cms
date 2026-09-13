<?php

declare(strict_types=1);

namespace ReaCms\Events;

use InvalidArgumentException;
use ReaCms\Plugin\SafeHtml;

final class EventValidator
{
    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        $values = [];
        foreach (
            ['title', 'short_description', 'description', 'location', 'address', 'city', 'state', 'zip',
            'country', 'start_date', 'start_time', 'end_date', 'end_time', 'timezone', 'url'] as $field
        ) {
            $value = $input[$field] ?? '';
            if (!is_string($value)) {
                throw new InvalidArgumentException('Event fields must have the correct scalar type.');
            }
            $limit = in_array($field, ['description', 'short_description'], true)
                ? 60000 : ($field === 'url' ? 1000 : 255);
            if (strlen($value) > $limit) {
                throw new InvalidArgumentException('The ' . $field . ' field is too long.');
            }
            $values[$field] = trim($value);
        }
        if ($values['title'] === '') {
            throw new InvalidArgumentException('An event title is required.');
        }
        $values['description'] = SafeHtml::sanitize($values['description'])->value;
        $values['short_description'] = SafeHtml::sanitize($values['short_description'])->value;
        foreach (['all_day', 'published'] as $field) {
            $value = $input[$field] ?? false;
            if (!in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
                throw new InvalidArgumentException('Invalid boolean: ' . $field);
            }
            $values[$field] = in_array($value, [true, 1, '1', 'true'], true) ? 1 : 0;
        }
        foreach (['type_id', 'image_id'] as $field) {
            $value = $input[$field] ?? null;
            $id = $value === null || $value === '' ? null : filter_var($value, FILTER_VALIDATE_INT);
            if ($id !== null && (!is_int($id) || $id < 1)) {
                throw new InvalidArgumentException('Choose a valid event type or image.');
            }
            $values[$field] = $id;
        }
        if (
            $values['url'] !== '' && (filter_var($values['url'], FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string) parse_url($values['url'], PHP_URL_SCHEME)), ['https', 'http'], true))
        ) {
            throw new InvalidArgumentException('The event website must be an HTTP or HTTPS URL.');
        }
        if ($values['end_date'] === '') {
            $values['end_date'] = $values['start_date'];
        }
        if ($values['all_day']) {
            $values['start_time'] = $values['end_time'] = '';
        }
        $start = EventDates::local($values['start_date'], $values['start_time'] ?: '00:00', $values['timezone']);
        $end = EventDates::local($values['end_date'], $values['end_time'] ?: '00:00', $values['timezone']);
        if (!$values['all_day'] && ($values['start_time'] === '' || $values['end_time'] === '')) {
            throw new InvalidArgumentException('Timed events need both start and end times.');
        }
        if ($values['all_day']) {
            $end = $end->modify('+1 day');
        }
        if ($end <= $start) {
            throw new InvalidArgumentException('The event must end after it starts.');
        }
        $values['start_utc'] = EventDates::utc($start);
        $values['end_utc'] = EventDates::utc($end);
        return $values;
    }
}
