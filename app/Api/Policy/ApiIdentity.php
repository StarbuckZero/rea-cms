<?php

declare(strict_types=1);

namespace ReaCms\Api\Policy;

final class ApiIdentity
{
    /** @param list<string> $scopes */
    public function __construct(
        public readonly ?int $userId = null,
        public readonly ?int $tokenId = null,
        public readonly array $scopes = [],
        public readonly ?string $tokenCidr = null,
    ) {
    }
}
