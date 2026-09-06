<?php

declare(strict_types=1);

namespace ReaCms\Auth;

interface RoleLookup
{
    public function hasRole(int $userId, string $role): bool;
}
