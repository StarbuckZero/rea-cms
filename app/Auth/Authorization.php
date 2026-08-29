<?php

declare(strict_types=1);

namespace ReaCms\Auth;

interface Authorization
{
    public function allows(int $userId, string $permission): bool;
}
