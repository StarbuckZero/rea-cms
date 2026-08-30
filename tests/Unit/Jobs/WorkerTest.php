<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use ReaCms\Jobs\Job;
use ReaCms\Jobs\JobQueue;
use ReaCms\Jobs\Worker;

final class WorkerTest extends TestCase
{
    public function testWorkerCompletesAllowlistedJobAndFailsUnknownJob(): void
    {
        $queue = new class implements JobQueue {
            /** @var list<Job> */ public array $jobs = [];
            public int $completed = 0;
            public int $failed = 0;
            public function push(
                string $queue,
                string $type,
                array $payload,
                ?string $idempotencyKey = null,
            ): void {
            }
            public function reserve(string $queue, int $reservationSeconds = 300): ?Job
            {
                return array_shift($this->jobs);
            }
            public function complete(Job $job): void
            {
                $this->completed++;
            }
            public function fail(Job $job, string $reason): void
            {
                $this->failed++;
            }
        };
        $handled = 0;
        $worker = new Worker($queue, [
            'publish' => static function (array $payload) use (&$handled): void {
                $handled++;
            },
        ]);
        $queue->jobs[] = new Job(1, 'publish', [], 1, 3, str_repeat('a', 32));
        $queue->jobs[] = new Job(2, 'shell', [], 1, 3, str_repeat('b', 32));

        self::assertTrue($worker->runOne('default'));
        self::assertTrue($worker->runOne('default'));
        self::assertSame(1, $handled);
        self::assertSame(1, $queue->completed);
        self::assertSame(1, $queue->failed);
    }
}
