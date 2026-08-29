<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use DateTimeImmutable;
use ReaCms\Auth\SessionRecord;
use ReaCms\Auth\SessionRepository;
use ReaCms\Auth\UserSession;

final class InMemorySessionRepository implements SessionRepository
{
    /** @var array<string, SessionRecord> */
    public array $records = [];

    /** @var list<string> */
    public array $revoked = [];

    /** @var list<int> */
    public array $revokedUsers = [];

    public function findActive(string $tokenHash, DateTimeImmutable $now): ?SessionRecord
    {
        $record = $this->records[$tokenHash] ?? null;

        return $record !== null && $record->expiresAt > $now && !in_array($tokenHash, $this->revoked, true)
            ? $record
            : null;
    }

    public function create(
        string $tokenHash,
        ?int $userId,
        string $ipAddress,
        string $userAgentHash,
        DateTimeImmutable $expiresAt,
    ): void {
        $this->records[$tokenHash] = new SessionRecord($tokenHash, $userId, $expiresAt);
    }

    public function touch(string $tokenHash, DateTimeImmutable $now): void
    {
    }

    public function revoke(string $tokenHash, DateTimeImmutable $now): void
    {
        $this->revoked[] = $tokenHash;
    }

    public function revokeUserSessions(int $userId, DateTimeImmutable $now): void
    {
        $this->revokedUsers[] = $userId;
    }

    public function markReauthenticated(string $tokenHash, DateTimeImmutable $now): void
    {
        $record = $this->records[$tokenHash];
        $this->records[$tokenHash] = new SessionRecord(
            $record->tokenHash,
            $record->userId,
            $record->expiresAt,
            $now,
        );
    }

    public function listForUser(int $userId, DateTimeImmutable $now): array
    {
        $sessions = [];
        foreach ($this->records as $record) {
            if ($record->userId === $userId && !in_array($record->tokenHash, $this->revoked, true)) {
                $sessions[] = new UserSession($record->tokenHash, '127.0.0.1', $now, $record->expiresAt);
            }
        }

        return $sessions;
    }

    public function revokeForUser(int $userId, string $tokenHash, DateTimeImmutable $now): bool
    {
        $record = $this->records[$tokenHash] ?? null;
        if ($record === null || $record->userId !== $userId) {
            return false;
        }

        $this->revoked[] = $tokenHash;

        return true;
    }
}
