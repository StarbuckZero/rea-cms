<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use ReaCms\Auth\Totp;

final class TotpTest extends TestCase
{
    public function testItMatchesTheRfcSha1VectorTruncatedToSixDigits(): void
    {
        $totp = new Totp();
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        self::assertSame('287082', $totp->code($secret, 1));
        self::assertTrue($totp->verify($secret, '287082', 59, 0));
        self::assertFalse($totp->verify($secret, '287083', 59, 0));
    }

    public function testGeneratedSecretsCanProduceCodes(): void
    {
        $totp = new Totp();
        $secret = $totp->generateSecret();

        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        self::assertMatchesRegularExpression('/^[0-9]{6}$/', $totp->code($secret, 100));
    }
}
