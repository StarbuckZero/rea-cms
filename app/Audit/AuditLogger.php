<?php

declare(strict_types=1);

namespace ReaCms\Audit;

interface AuditLogger
{
    /**
     * @param array<string, bool|int|float|string|null> $metadata
     */
    public function record(
        string $eventType,
        ?int $actorUserId,
        string $ipAddress,
        string $requestId,
        array $metadata = [],
        ?string $subjectType = null,
        ?string $subjectId = null,
    ): void;
}
