<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use DateTimeImmutable;

interface PasswordResetRepository
{
    public function invalidateForUser(int $userId, DateTimeImmutable $now): void;

    public function create(string $tokenHash, int $userId, DateTimeImmutable $expiresAt): void;

    public function findActive(string $tokenHash, DateTimeImmutable $now): ?PasswordResetRecord;

    public function markUsed(string $tokenHash, DateTimeImmutable $now): void;
}
