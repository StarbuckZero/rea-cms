<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Setup;

use PHPUnit\Framework\TestCase;
use ReaCms\Setup\SetupTokenStore;

final class SetupTokenStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/rea-cms-token-' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/storage', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/storage/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory . '/storage');
        rmdir($this->directory);
    }

    public function testTokensAreValidOnlyOnce(): void
    {
        $store = new SetupTokenStore($this->directory);
        $token = $store->issue();

        self::assertTrue($store->consume($token));
        self::assertFalse($store->consume($token));
    }
}
