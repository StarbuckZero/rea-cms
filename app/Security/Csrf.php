<?php

declare(strict_types=1);

namespace ReaCms\Security;

use RuntimeException;

final class Csrf
{
    public function __construct(private readonly string $applicationKey)
    {
        if (strlen($applicationKey) < 32) {
            throw new RuntimeException('APP_KEY must contain at least 32 characters.');
        }
    }

    public function token(string $sessionToken): string
    {
        return hash_hmac('sha256', 'rea-cms-csrf-v1|' . $sessionToken, $this->applicationKey);
    }

    public function validate(string $sessionToken, ?string $provided): bool
    {
        return is_string($provided) && hash_equals($this->token($sessionToken), $provided);
    }
}
