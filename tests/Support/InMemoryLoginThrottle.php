<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use DateTimeImmutable;
use ReaCms\Auth\LoginThrottle;

final class InMemoryLoginThrottle implements LoginThrottle
{
    public bool $locked = false;

    /** @var list<string> */
    public array $failures = [];

    /** @var list<string> */
    public array $cleared = [];

    public function isLocked(string $key, DateTimeImmutable $now): bool
    {
        return $this->locked;
    }

    public function recordFailure(string $key, DateTimeImmutable $now): void
    {
        $this->failures[] = $key;
    }

    public function clear(string $key): void
    {
        $this->cleared[] = $key;
    }
}
