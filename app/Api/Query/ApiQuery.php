<?php

declare(strict_types=1);

namespace ReaCms\Api\Query;

use InvalidArgumentException;

final class ApiQuery
{
    /** @param array<string, string> $filters */
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly array $filters,
        public readonly ?string $sort,
        public readonly string $direction,
    ) {
    }

    /**
     * @param array<string, string|list<string>> $query
     * @param list<string> $allowedFilters
     * @param list<string> $allowedSorts
     */
    public static function fromArray(array $query, array $allowedFilters, array $allowedSorts, int $maximum = 100): self
    {
        $page = self::positive($query['page'] ?? '1', 'page');
        $perPage = min(self::positive($query['perPage'] ?? '20', 'perPage'), $maximum);
        $filters = [];

        foreach ($query as $key => $value) {
            if (!str_starts_with($key, 'filter_')) {
                continue;
            }

            $field = substr($key, 7);
            if (!in_array($field, $allowedFilters, true) || !is_string($value)) {
                throw new InvalidArgumentException('Unsupported API filter: ' . $field);
            }
            $filters[$field] = $value;
        }

        $sort = isset($query['sort']) && is_string($query['sort']) ? $query['sort'] : null;
        if ($sort !== null && !in_array($sort, $allowedSorts, true)) {
            throw new InvalidArgumentException('Unsupported API sort: ' . $sort);
        }

        $direction = isset($query['direction']) && is_string($query['direction'])
            ? strtolower($query['direction'])
            : 'asc';
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('API sort direction must be asc or desc.');
        }

        return new self($page, $perPage, $filters, $sort, $direction);
    }

    /** @param string|list<string> $value */
    private static function positive(string|array $value, string $name): int
    {
        $parsed = is_string($value)
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        if (!is_int($parsed)) {
            throw new InvalidArgumentException($name . ' must be a positive integer.');
        }
        return $parsed;
    }
}
