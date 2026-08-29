<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use DateInterval;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PdoLoginThrottle implements LoginThrottle
{
    private readonly string $table;

    public function __construct(
        private readonly PDO $pdo,
        string $prefix = 'rea_',
        private readonly int $maximumAttempts = 5,
        private readonly int $windowMinutes = 15,
        private readonly int $lockMinutes = 15,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }

        $this->table = $prefix . 'login_attempts';
    }

    public function isLocked(string $key, DateTimeImmutable $now): bool
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT `locked_until` FROM `%s` WHERE `attempt_key` = :attempt_key LIMIT 1',
            $this->table,
        ));
        $statement->execute(['attempt_key' => $key]);
        $lockedUntil = $statement->fetchColumn();

        return is_string($lockedUntil) && new DateTimeImmutable($lockedUntil) > $now;
    }

    public function recordFailure(string $key, DateTimeImmutable $now): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT `attempt_count`, `window_started_at` FROM `%s` WHERE `attempt_key` = :attempt_key LIMIT 1',
            $this->table,
        ));
        $statement->execute(['attempt_key' => $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $windowStart = $now;
        $attempts = 1;

        if (is_array($row)) {
            $storedStart = new DateTimeImmutable((string) $row['window_started_at']);
            if ($storedStart->add(new DateInterval(sprintf('PT%dM', $this->windowMinutes))) > $now) {
                $windowStart = $storedStart;
                $attempts = (int) $row['attempt_count'] + 1;
            }
        }

        $lockedUntil = $attempts >= $this->maximumAttempts
            ? $now->add(new DateInterval(sprintf('PT%dM', $this->lockMinutes)))
            : null;

        $upsert = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s` (`attempt_key`, `attempt_count`, `window_started_at`, `locked_until`) '
                . 'VALUES (:attempt_key, :attempt_count, :window_started_at, :locked_until) '
                . 'ON DUPLICATE KEY UPDATE `attempt_count` = VALUES(`attempt_count`), '
                . '`window_started_at` = VALUES(`window_started_at`), `locked_until` = VALUES(`locked_until`)',
            $this->table,
        ));
        $upsert->execute([
            'attempt_key' => $key,
            'attempt_count' => $attempts,
            'window_started_at' => self::format($windowStart),
            'locked_until' => $lockedUntil === null ? null : self::format($lockedUntil),
        ]);
    }

    public function clear(string $key): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'DELETE FROM `%s` WHERE `attempt_key` = :attempt_key',
            $this->table,
        ));
        $statement->execute(['attempt_key' => $key]);
    }

    private static function format(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
    }
}
