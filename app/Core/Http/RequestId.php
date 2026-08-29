<?php

declare(strict_types=1);

namespace ReaCms\Core\Http;

final class RequestId
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
