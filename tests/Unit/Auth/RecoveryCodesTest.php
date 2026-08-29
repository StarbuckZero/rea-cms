<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use ReaCms\Auth\RecoveryCodes;

final class RecoveryCodesTest extends TestCase
{
    public function testPlaintextCodesAreShownSeparatelyFromStoredHashes(): void
    {
        $codes = new RecoveryCodes();
        $generated = $codes->generate(3);

        self::assertCount(3, $generated['plain']);
        self::assertCount(3, $generated['hashes']);
        self::assertNotSame($generated['plain'][0], $generated['hashes'][0]);
        self::assertTrue($codes->verify($generated['plain'][0], $generated['hashes'][0]));
        self::assertFalse($codes->verify('WRONG-CODE', $generated['hashes'][0]));
    }
}
