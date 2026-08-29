<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use DateTimeImmutable;
use ReaCms\Auth\PasswordResetRecord;
use ReaCms\Auth\PasswordResetRepository;

final class InMemoryPasswordResetRepository implements PasswordResetRepository
{
    /** @var array<string, PasswordResetRecord> */
    public array $records = [];

    /** @var list<string> */
    public array $used = [];

    public function invalidateForUser(int $userId, DateTimeImmutable $now): void
    {
        foreach ($this->records as $hash => $record) {
            if ($record->userId === $userId) {
                $this->used[] = $hash;
            }
        }
    }

    public function create(string $tokenHash, int $userId, DateTimeImmutable $expiresAt): void
    {
        $this->records[$tokenHash] = new PasswordResetRecord($tokenHash, $userId, $expiresAt);
    }

    public function findActive(string $tokenHash, DateTimeImmutable $now): ?PasswordResetRecord
    {
        $record = $this->records[$tokenHash] ?? null;

        return $record !== null && $record->expiresAt > $now && !in_array($tokenHash, $this->used, true)
            ? $record
            : null;
    }

    public function markUsed(string $tokenHash, DateTimeImmutable $now): void
    {
        $this->used[] = $tokenHash;
    }
}
