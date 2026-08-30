<?php

declare(strict_types=1);

namespace ReaCms\Api\RateLimit;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class PdoRateLimiter implements RateLimiter
{
    private readonly string $table;

    public function __construct(private readonly PDO $pdo, string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }
        $this->table = $prefix . 'rate_limits';
    }

    public function consume(string $bucket, string $identity, int $maximum, int $windowSeconds): RateLimitDecision
    {
        if ($maximum < 1 || $windowSeconds < 1) {
            throw new RuntimeException('Rate-limit values must be positive.');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $window = intdiv($now->getTimestamp(), $windowSeconds) * $windowSeconds;
        $windowStart = (new DateTimeImmutable('@' . $window))->setTimezone(new DateTimeZone('UTC'));
        $expires = (new DateTimeImmutable('@' . ($window + $windowSeconds)))->setTimezone(new DateTimeZone('UTC'));
        $key = hash('sha256', $bucket . "\0" . $identity . "\0" . $window);

        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s` (limit_key, attempt_count, window_started_at, expires_at) '
            . 'VALUES (:key, 1, :started, :expires) '
            . 'ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1',
            $this->table,
        ));
        $statement->execute([
            'key' => $key,
            'started' => $windowStart->format('Y-m-d H:i:s'),
            'expires' => $expires->format('Y-m-d H:i:s'),
        ]);

        $select = $this->pdo->prepare(sprintf('SELECT attempt_count FROM `%s` WHERE limit_key = :key', $this->table));
        $select->execute(['key' => $key]);
        $attempts = (int) $select->fetchColumn();

        return new RateLimitDecision(
            $attempts <= $maximum,
            max(0, $maximum - $attempts),
            max(1, $expires->getTimestamp() - $now->getTimestamp()),
        );
    }
}
