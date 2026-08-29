<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use ReaCms\Security\SecretCipher;
use RuntimeException;

final class SecretCipherTest extends TestCase
{
    public function testItEncryptsAndAuthenticatesSecrets(): void
    {
        $cipher = new SecretCipher(str_repeat('k', 64));
        $payload = $cipher->encrypt('totp-secret');

        self::assertNotSame('totp-secret', $payload);
        self::assertSame('totp-secret', $cipher->decrypt($payload));
    }

    public function testTheWrongKeyCannotDecryptASecret(): void
    {
        $payload = (new SecretCipher(str_repeat('a', 64)))->encrypt('secret');

        $this->expectException(RuntimeException::class);

        (new SecretCipher(str_repeat('b', 64)))->decrypt($payload);
    }
}
