<?php

declare(strict_types=1);

namespace ReaCms\Api\RateLimit;

interface RateLimiter
{
    public function consume(string $bucket, string $identity, int $maximum, int $windowSeconds): RateLimitDecision;
}
