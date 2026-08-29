<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use ReaCms\Security\Csrf;
use RuntimeException;

final class CsrfTest extends TestCase
{
    public function testTokensAreBoundToTheSessionAndApplicationKey(): void
    {
        $csrf = new Csrf(str_repeat('a', 64));
        $token = $csrf->token('session-one');

        self::assertTrue($csrf->validate('session-one', $token));
        self::assertFalse($csrf->validate('session-two', $token));
        self::assertFalse((new Csrf(str_repeat('b', 64)))->validate('session-one', $token));
        self::assertFalse($csrf->validate('session-one', null));
    }

    public function testShortApplicationKeysAreRejected(): void
    {
        $this->expectException(RuntimeException::class);

        new Csrf('short');
    }
}
