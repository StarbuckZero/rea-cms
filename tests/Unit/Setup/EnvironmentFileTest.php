<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Setup;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use ReaCms\Setup\EnvironmentFile;
use RuntimeException;

final class EnvironmentFileTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/rea-cms-env-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/.*') ?: [] as $file) {
            if (basename($file) !== '.' && basename($file) !== '..') {
                unlink($file);
            }
        }
        rmdir($this->directory);
    }

    public function testItWritesSpecialCharactersWithoutChangingTheirValues(): void
    {
        $file = new EnvironmentFile($this->directory);
        $values = [
            'APP_ENV' => 'production',
            'DB_PASSWORD' => ' spaces # $dollar "quote" \\ slash ',
        ];

        $file->create($values);
        $loaded = Dotenv::createArrayBacked($this->directory)->load();

        self::assertSame($values, $loaded);
        self::assertSame(0600, fileperms($this->directory . '/.env') & 0777);
    }

    public function testItRefusesToOverwriteAnExistingEnvironment(): void
    {
        file_put_contents($this->directory . '/.env', "APP_ENV=existing\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already configured');

        (new EnvironmentFile($this->directory))->create(['APP_ENV' => 'production']);
    }
}
