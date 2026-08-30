<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Api\Query;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReaCms\Api\Query\ApiQuery;

final class ApiQueryTest extends TestCase
{
    public function testPaginationIsBoundedAndFiltersAndSortsAreAllowlisted(): void
    {
        $query = ApiQuery::fromArray(
            ['page' => '2', 'perPage' => '999', 'filter_status' => 'ok', 'sort' => 'service', 'direction' => 'desc'],
            ['status'],
            ['service'],
        );

        self::assertSame(2, $query->page);
        self::assertSame(100, $query->perPage);
        self::assertSame(['status' => 'ok'], $query->filters);
        self::assertSame('service', $query->sort);
        self::assertSame('desc', $query->direction);
    }

    public function testUnknownFilterIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ApiQuery::fromArray(['filter_secret' => 'x'], ['status'], ['service']);
    }
}
