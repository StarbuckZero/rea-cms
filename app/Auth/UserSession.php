<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use DateTimeImmutable;

final class UserSession
{
    public function __construct(
        public readonly string $tokenHash,
        public readonly string $ipAddress,
        public readonly DateTimeImmutable $lastSeenAt,
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }
}
