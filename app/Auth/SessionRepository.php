<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use DateTimeImmutable;

interface SessionRepository
{
    public function findActive(string $tokenHash, DateTimeImmutable $now): ?SessionRecord;

    public function create(
        string $tokenHash,
        ?int $userId,
        string $ipAddress,
        string $userAgentHash,
        DateTimeImmutable $expiresAt,
    ): void;

    public function touch(string $tokenHash, DateTimeImmutable $now): void;

    public function revoke(string $tokenHash, DateTimeImmutable $now): void;

    public function revokeUserSessions(int $userId, DateTimeImmutable $now): void;

    public function markReauthenticated(string $tokenHash, DateTimeImmutable $now): void;

    /**
     * @return list<UserSession>
     */
    public function listForUser(int $userId, DateTimeImmutable $now): array;

    public function revokeForUser(int $userId, string $tokenHash, DateTimeImmutable $now): bool;
}
