<?php

declare(strict_types=1);

namespace ReaCms\Events;

use InvalidArgumentException;
use ReaCms\Api\Query\ApiQuery;

final class EventQuery
{
    /** @param array<string, string|list<string>> $input */
    public static function parse(array $input): ApiQuery
    {
        $filters = ['search', 'type', 'date', 'start', 'end', 'upcoming', 'past'];
        foreach ($filters as $key) {
            if (isset($input[$key]) && $input[$key] !== '') {
                $input['filter_' . $key] = $input[$key];
            }
        }
        $query = ApiQuery::fromArray($input, $filters, ['date', 'date-desc', 'name', 'name-desc']);
        foreach (['date', 'start', 'end'] as $key) {
            if (isset($query->filters[$key]) && !EventDates::date($query->filters[$key])) {
                throw new InvalidArgumentException('Date filters must use YYYY-MM-DD.');
            }
        }
        if (
            isset($query->filters['start'], $query->filters['end'])
            && $query->filters['start'] > $query->filters['end']
        ) {
            throw new InvalidArgumentException('The date range end must not precede its start.');
        }
        foreach (['upcoming', 'past'] as $key) {
            if (isset($query->filters[$key]) && !in_array($query->filters[$key], ['true', 'false'], true)) {
                throw new InvalidArgumentException('Upcoming and past filters must be true or false.');
            }
        }
        if (($query->filters['upcoming'] ?? '') === 'true' && ($query->filters['past'] ?? '') === 'true') {
            throw new InvalidArgumentException('Upcoming and past cannot both be true.');
        }
        if ($query->page > intdiv(PHP_INT_MAX, $query->perPage)) {
            throw new InvalidArgumentException('The requested page is too large.');
        }
        return $query;
    }
}
