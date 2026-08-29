<?php

declare(strict_types=1);

namespace ReaCms\Auth;

final class SessionContext
{
    public function __construct(
        public readonly string $token,
        public readonly SessionRecord $record,
        public readonly bool $isNew,
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->record->userId !== null;
    }
}
