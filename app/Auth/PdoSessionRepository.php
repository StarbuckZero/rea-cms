<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PdoSessionRepository implements SessionRepository
{
    private readonly string $table;

    public function __construct(private readonly PDO $pdo, string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }

        $this->table = $prefix . 'sessions';
    }

    public function findActive(string $tokenHash, DateTimeImmutable $now): ?SessionRecord
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT `token_hash`, `user_id`, `expires_at`, `reauthenticated_at` FROM `%s` '
                . 'WHERE `token_hash` = :token_hash AND `revoked_at` IS NULL AND `expires_at` > :now LIMIT 1',
            $this->table,
        ));
        $statement->execute(['token_hash' => $tokenHash, 'now' => self::format($now)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return new SessionRecord(
            (string) $row['token_hash'],
            $row['user_id'] === null ? null : (int) $row['user_id'],
            new DateTimeImmutable((string) $row['expires_at']),
            $row['reauthenticated_at'] === null ? null : new DateTimeImmutable((string) $row['reauthenticated_at']),
        );
    }

    public function create(
        string $tokenHash,
        ?int $userId,
        string $ipAddress,
        string $userAgentHash,
        DateTimeImmutable $expiresAt,
    ): void {
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s` '
                . '(`token_hash`, `user_id`, `ip_address`, `user_agent_hash`, `expires_at`) '
                . 'VALUES (:token_hash, :user_id, :ip_address, :user_agent_hash, :expires_at)',
            $this->table,
        ));
        $statement->execute([
            'token_hash' => $tokenHash,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent_hash' => $userAgentHash,
            'expires_at' => self::format($expiresAt),
        ]);
    }

    public function touch(string $tokenHash, DateTimeImmutable $now): void
    {
        $this->updateTimestamp('last_seen_at', $tokenHash, $now);
    }

    public function revoke(string $tokenHash, DateTimeImmutable $now): void
    {
        $this->updateTimestamp('revoked_at', $tokenHash, $now);
    }

    public function revokeUserSessions(int $userId, DateTimeImmutable $now): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET `revoked_at` = :now WHERE `user_id` = :user_id AND `revoked_at` IS NULL',
            $this->table,
        ));
        $statement->execute(['now' => self::format($now), 'user_id' => $userId]);
    }

    public function markReauthenticated(string $tokenHash, DateTimeImmutable $now): void
    {
        $this->updateTimestamp('reauthenticated_at', $tokenHash, $now);
    }

    public function listForUser(int $userId, DateTimeImmutable $now): array
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT `token_hash`, `ip_address`, `last_seen_at`, `expires_at` FROM `%s` '
                . 'WHERE `user_id` = :user_id AND `revoked_at` IS NULL AND `expires_at` > :now '
                . 'ORDER BY `last_seen_at` DESC',
            $this->table,
        ));
        $statement->execute(['user_id' => $userId, 'now' => self::format($now)]);
        $sessions = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $sessions[] = new UserSession(
                (string) $row['token_hash'],
                (string) $row['ip_address'],
                new DateTimeImmutable((string) $row['last_seen_at']),
                new DateTimeImmutable((string) $row['expires_at']),
            );
        }

        return $sessions;
    }

    public function revokeForUser(int $userId, string $tokenHash, DateTimeImmutable $now): bool
    {
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET `revoked_at` = :now '
                . 'WHERE `user_id` = :user_id AND `token_hash` = :token_hash AND `revoked_at` IS NULL',
            $this->table,
        ));
        $statement->execute([
            'now' => self::format($now),
            'user_id' => $userId,
            'token_hash' => $tokenHash,
        ]);

        return $statement->rowCount() === 1;
    }

    private function updateTimestamp(string $column, string $tokenHash, DateTimeImmutable $now): void
    {
        $allowed = ['last_seen_at', 'revoked_at', 'reauthenticated_at'];
        if (!in_array($column, $allowed, true)) {
            throw new RuntimeException('The session timestamp column is invalid.');
        }

        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET `%s` = :now WHERE `token_hash` = :token_hash',
            $this->table,
            $column,
        ));
        $statement->execute(['now' => self::format($now), 'token_hash' => $tokenHash]);
    }

    private static function format(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
    }
}
