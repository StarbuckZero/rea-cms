<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use ReaCms\Audit\AuditLogger;

final class InMemoryAuditLogger implements AuditLogger
{
    /** @var list<array{event: string, actor: int|null, metadata: array<string, bool|int|float|string|null>}> */
    public array $events = [];

    public function record(
        string $eventType,
        ?int $actorUserId,
        string $ipAddress,
        string $requestId,
        array $metadata = [],
        ?string $subjectType = null,
        ?string $subjectId = null,
    ): void {
        $this->events[] = [
            'event' => $eventType,
            'actor' => $actorUserId,
            'metadata' => $metadata,
        ];
    }
}
