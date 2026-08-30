<?php

declare(strict_types=1);

namespace ReaCms\Jobs;

final class Job
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly array $payload,
        public readonly int $attempts,
        public readonly int $maxAttempts,
        public readonly string $reservationToken,
    ) {
    }
}
