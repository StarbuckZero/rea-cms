<?php

declare(strict_types=1);

namespace ReaCms\Api\Token;

use DateTimeImmutable;

interface ApiTokenRepository
{
    public function findActive(string $tokenId, DateTimeImmutable $now): ?ApiToken;

    public function touch(int $id, DateTimeImmutable $now): void;
}
