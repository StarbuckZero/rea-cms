<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PdoPasswordResetRepository implements PasswordResetRepository
{
    private readonly string $table;

    public function __construct(private readonly PDO $pdo, string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }

        $this->table = $prefix . 'password_resets';
    }

    public function invalidateForUser(int $userId, DateTimeImmutable $now): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET `used_at` = :now WHERE `user_id` = :user_id AND `used_at` IS NULL',
            $this->table,
        ));
        $statement->execute(['now' => self::format($now), 'user_id' => $userId]);
    }

    public function create(string $tokenHash, int $userId, DateTimeImmutable $expiresAt): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s` (`token_hash`, `user_id`, `expires_at`) '
                . 'VALUES (:token_hash, :user_id, :expires_at)',
            $this->table,
        ));
        $statement->execute([
            'token_hash' => $tokenHash,
            'user_id' => $userId,
            'expires_at' => self::format($expiresAt),
        ]);
    }

    public function findActive(string $tokenHash, DateTimeImmutable $now): ?PasswordResetRecord
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT `token_hash`, `user_id`, `expires_at` FROM `%s` '
                . 'WHERE `token_hash` = :token_hash AND `used_at` IS NULL AND `expires_at` > :now LIMIT 1',
            $this->table,
        ));
        $statement->execute(['token_hash' => $tokenHash, 'now' => self::format($now)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? new PasswordResetRecord(
            (string) $row['token_hash'],
            (int) $row['user_id'],
            new DateTimeImmutable((string) $row['expires_at']),
        ) : null;
    }

    public function markUsed(string $tokenHash, DateTimeImmutable $now): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET `used_at` = :now WHERE `token_hash` = :token_hash AND `used_at` IS NULL',
            $this->table,
        ));
        $statement->execute(['now' => self::format($now), 'token_hash' => $tokenHash]);
    }

    private static function format(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
    }
}
