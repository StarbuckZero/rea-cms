<?php

declare(strict_types=1);

namespace ReaCms\Api\Token;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PdoApiTokenRepository implements ApiTokenRepository
{
    private readonly string $tokens;
    private readonly string $scopes;

    public function __construct(private readonly PDO $pdo, string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }

        $this->tokens = $prefix . 'api_tokens';
        $this->scopes = $prefix . 'api_token_scopes';
    }

    public function findActive(string $tokenId, DateTimeImmutable $now): ?ApiToken
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT id, user_id, token_hash, ip_cidr, expires_at FROM `%s` '
            . 'WHERE token_id = :token_id AND revoked_at IS NULL '
            . 'AND (expires_at IS NULL OR expires_at > :now) LIMIT 1',
            $this->tokens,
        ));
        $statement->execute(['token_id' => $tokenId, 'now' => $now->format('Y-m-d H:i:s.u')]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        $scopeStatement = $this->pdo->prepare(sprintf(
            'SELECT scope FROM `%s` WHERE token_id = :token_id ORDER BY scope',
            $this->scopes,
        ));
        $scopeStatement->execute(['token_id' => (int) $row['id']]);
        $scopes = $scopeStatement->fetchAll(PDO::FETCH_COLUMN);

        return new ApiToken(
            (int) $row['id'],
            $row['user_id'] === null ? null : (int) $row['user_id'],
            (string) $row['token_hash'],
            array_values(array_filter($scopes, 'is_string')),
            $row['ip_cidr'] === null ? null : (string) $row['ip_cidr'],
            $row['expires_at'] === null ? null : new DateTimeImmutable((string) $row['expires_at']),
        );
    }

    public function touch(int $id, DateTimeImmutable $now): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET last_used_at = :now WHERE id = :id',
            $this->tokens,
        ));
        $statement->execute(['id' => $id, 'now' => $now->format('Y-m-d H:i:s.u')]);
    }
}
