<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Api\Token;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Api\Token\ApiToken;
use ReaCms\Api\Token\ApiTokenAuthenticator;
use ReaCms\Api\Token\ApiTokenRepository;
use ReaCms\Tests\Support\FrozenClock;

final class ApiTokenAuthenticatorTest extends TestCase
{
    public function testValidTokenReturnsScopesAndInvalidSecretIsRejected(): void
    {
        $generated = ApiTokenAuthenticator::generate();
        $repository = new class ($generated['tokenId'], $generated['hash']) implements ApiTokenRepository {
            public bool $touched = false;

            public function __construct(private string $tokenId, private string $hash)
            {
            }

            public function findActive(string $tokenId, DateTimeImmutable $now): ?ApiToken
            {
                return $tokenId === $this->tokenId
                    ? new ApiToken(4, 7, $this->hash, ['status:read'], null, null)
                    : null;
            }

            public function touch(int $id, DateTimeImmutable $now): void
            {
                $this->touched = true;
            }
        };
        $authenticator = new ApiTokenAuthenticator(
            $repository,
            new FrozenClock(new DateTimeImmutable('2026-08-29T12:00:00+00:00')),
        );

        $identity = $authenticator->authenticate('Bearer ' . $generated['plaintext']);
        self::assertSame(4, $identity->tokenId);
        self::assertSame(['status:read'], $identity->scopes);
        self::assertTrue($repository->touched);
        self::assertNull($authenticator->authenticate(
            'Bearer rea_' . $generated['tokenId'] . '_' . str_repeat('0', 64),
        )->tokenId);
    }
}
