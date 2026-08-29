<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use DateTimeImmutable;
use ReaCms\Support\Clock;

final class FrozenClock implements Clock
{
    public function __construct(public DateTimeImmutable $current)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->current;
    }
}
