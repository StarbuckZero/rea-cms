<?php

declare(strict_types=1);

namespace ReaCms\Auth;

final class LoginResult
{
    private function __construct(
        public readonly ?User $user,
        public readonly bool $locked,
    ) {
    }

    public static function success(User $user): self
    {
        return new self($user, false);
    }

    public static function failure(bool $locked = false): self
    {
        return new self(null, $locked);
    }

    public function succeeded(): bool
    {
        return $this->user !== null;
    }
}
