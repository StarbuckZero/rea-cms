<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use ReaCms\Auth\Authorization;

final class InMemoryAuthorization implements Authorization
{
    /** @var array<int, list<string>> */
    public array $permissions = [];

    public function allows(int $userId, string $permission): bool
    {
        return in_array($permission, $this->permissions[$userId] ?? [], true);
    }
}
