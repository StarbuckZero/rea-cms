<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use ReaCms\Auth\RoleLookup;

final class InMemoryRoleLookup implements RoleLookup
{
    /** @var array<int, list<string>> */
    public array $roles = [];

    public function hasRole(int $userId, string $role): bool
    {
        return in_array($role, $this->roles[$userId] ?? [], true);
    }
}
