<?php

declare(strict_types=1);

namespace ReaCms\Audit;

use JsonException;
use PDO;
use RuntimeException;

final class PdoAuditLogger implements AuditLogger
{
    private readonly string $table;

    public function __construct(private readonly PDO $pdo, string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }

        $this->table = $prefix . 'audit_log';
    }

    /**
     * @throws JsonException
     */
    public function record(
        string $eventType,
        ?int $actorUserId,
        string $ipAddress,
        string $requestId,
        array $metadata = [],
        ?string $subjectType = null,
        ?string $subjectId = null,
    ): void {
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s` '
                . '(`actor_user_id`, `event_type`, `subject_type`, `subject_id`, `ip_address`, '
                . '`request_id`, `metadata_json`) VALUES '
                . '(:actor_user_id, :event_type, :subject_type, :subject_id, :ip_address, '
                . ':request_id, :metadata_json)',
            $this->table,
        ));
        $statement->execute([
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'ip_address' => $ipAddress,
            'request_id' => $requestId,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }
}
