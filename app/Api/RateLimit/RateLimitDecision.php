<?php

declare(strict_types=1);

namespace ReaCms\Api\RateLimit;

final class RateLimitDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $remaining,
        public readonly int $retryAfter,
    ) {
    }
}
