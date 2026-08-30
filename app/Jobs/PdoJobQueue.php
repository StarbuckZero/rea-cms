<?php

declare(strict_types=1);

namespace ReaCms\Jobs;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PDO;
use RuntimeException;

final class PdoJobQueue implements JobQueue
{
    private string $jobs;
    private string $failed;

    public function __construct(private readonly PDO $pdo, string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }
        $this->jobs = $prefix . 'jobs';
        $this->failed = $prefix . 'failed_jobs';
    }

    public function push(string $queue, string $type, array $payload, ?string $idempotencyKey = null): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'INSERT IGNORE INTO `%s` (queue, job_type, payload_json, idempotency_key, available_at) '
            . 'VALUES (:queue, :type, :payload, :idempotency, UTC_TIMESTAMP(6))',
            $this->jobs,
        ));
        $statement->execute([
            'queue' => $queue,
            'type' => $type,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'idempotency' => $idempotencyKey,
        ]);
    }

    public function reserve(string $queue, int $reservationSeconds = 300): ?Job
    {
        $token = bin2hex(random_bytes(16));
        $this->pdo->beginTransaction();
        try {
            $select = $this->pdo->prepare(sprintf(
                'SELECT id FROM `%s` WHERE queue = :queue AND available_at <= UTC_TIMESTAMP(6) '
                . 'AND (reserved_at IS NULL OR reserved_at < DATE_SUB(UTC_TIMESTAMP(6), INTERVAL :timeout SECOND)) '
                . 'ORDER BY id LIMIT 1 FOR UPDATE',
                $this->jobs,
            ));
            $select->bindValue('queue', $queue);
            $select->bindValue('timeout', max(1, $reservationSeconds), PDO::PARAM_INT);
            $select->execute();
            $id = $select->fetchColumn();
            if ($id === false) {
                $this->pdo->commit();
                return null;
            }
            $update = $this->pdo->prepare(sprintf(
                'UPDATE `%s` SET attempts = attempts + 1, reserved_at = UTC_TIMESTAMP(6), reservation_token = :token '
                . 'WHERE id = :id',
                $this->jobs,
            ));
            $update->execute(['token' => $token, 'id' => $id]);
            $rowStatement = $this->pdo->prepare(sprintf(
                'SELECT id, job_type, payload_json, attempts, max_attempts FROM `%s` WHERE id = :id',
                $this->jobs,
            ));
            $rowStatement->execute(['id' => $id]);
            $row = $rowStatement->fetch();
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
        if (!is_array($row)) {
            return null;
        }
        try {
            $payload = json_decode((string) $row['payload_json'], true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('A queued job payload is invalid.', previous: $exception);
        }
        return new Job(
            (int) $row['id'],
            (string) $row['job_type'],
            is_array($payload) ? $payload : [],
            (int) $row['attempts'],
            (int) $row['max_attempts'],
            $token
        );
    }

    public function complete(Job $job): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'DELETE FROM `%s` WHERE id = :id AND reservation_token = :token',
            $this->jobs,
        ));
        $statement->execute(['id' => $job->id, 'token' => $job->reservationToken]);
    }

    public function fail(Job $job, string $reason): void
    {
        if ($job->attempts < $job->maxAttempts) {
            $statement = $this->pdo->prepare(sprintf(
                'UPDATE `%s` SET reserved_at = NULL, reservation_token = NULL, '
                . 'available_at = DATE_ADD(UTC_TIMESTAMP(6), INTERVAL :delay SECOND) '
                . 'WHERE id = :id AND reservation_token = :token',
                $this->jobs,
            ));
            $statement->execute(['delay' => min(3600, 2 ** $job->attempts * 30), 'id' => $job->id,
                'token' => $job->reservationToken]);
            return;
        }
        $this->pdo->beginTransaction();
        $copy = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s` (original_job_id, queue, job_type, payload_json, attempts, failure_reason) '
            . 'SELECT id, queue, job_type, payload_json, attempts, :reason FROM `%s` '
            . 'WHERE id = :id AND reservation_token = :token',
            $this->failed,
            $this->jobs,
        ));
        $copy->execute(['reason' => substr($reason, 0, 1000), 'id' => $job->id, 'token' => $job->reservationToken]);
        $this->complete($job);
        $this->pdo->commit();
    }
}
