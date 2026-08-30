<?php

declare(strict_types=1);

namespace ReaCms\Jobs;

interface JobQueue
{
    /** @param array<string, mixed> $payload */
    public function push(string $queue, string $type, array $payload, ?string $idempotencyKey = null): void;

    public function reserve(string $queue, int $reservationSeconds = 300): ?Job;

    public function complete(Job $job): void;

    public function fail(Job $job, string $reason): void;
}
