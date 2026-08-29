<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use DateTimeImmutable;

final class PasswordResetRecord
{
    public function __construct(
        public readonly string $tokenHash,
        public readonly int $userId,
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }
}
