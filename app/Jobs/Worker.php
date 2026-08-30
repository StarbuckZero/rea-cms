<?php

declare(strict_types=1);

namespace ReaCms\Jobs;

use Throwable;

final class Worker
{
    /** @param array<string, callable(array<string, mixed>): void> $handlers */
    public function __construct(private readonly JobQueue $queue, private readonly array $handlers)
    {
    }

    public function runOne(string $queue): bool
    {
        $job = $this->queue->reserve($queue);
        if ($job === null) {
            return false;
        }
        try {
            $handler = $this->handlers[$job->type] ?? null;
            if ($handler === null) {
                throw new \RuntimeException('No allowlisted handler exists for the job type.');
            }
            $handler($job->payload);
            $this->queue->complete($job);
        } catch (Throwable $exception) {
            $this->queue->fail($job, $exception->getMessage());
        }
        return true;
    }
}
