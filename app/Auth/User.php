<?php

declare(strict_types=1);

namespace ReaCms\Auth;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $status,
        public readonly string $displayName,
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
