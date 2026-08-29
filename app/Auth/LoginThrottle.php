<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use DateTimeImmutable;

interface LoginThrottle
{
    public function isLocked(string $key, DateTimeImmutable $now): bool;

    public function recordFailure(string $key, DateTimeImmutable $now): void;

    public function clear(string $key): void;
}
