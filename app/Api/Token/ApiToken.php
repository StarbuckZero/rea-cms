<?php

declare(strict_types=1);

namespace ReaCms\Api\Token;

use DateTimeImmutable;

final class ApiToken
{
    /** @param list<string> $scopes */
    public function __construct(
        public readonly int $id,
        public readonly ?int $userId,
        public readonly string $hash,
        public readonly array $scopes,
        public readonly ?string $ipCidr,
        public readonly ?DateTimeImmutable $expiresAt,
    ) {
    }
}
