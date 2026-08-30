<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;
use ReaCms\Operations\BackupDatabase;
use ReaCms\Operations\BackupException;
use ReaCms\Operations\BackupManager;

final class BackupManagerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/rea-backup-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testBackupRestoresAndVerifiesIntoCleanDatabase(): void
    {
        $source = $this->database(['rea_settings' => [['setting_key' => 'site.name', 'value' => 'Rea']]]);
        $path = (new BackupManager($source, $this->directory))->create(
            'full',
            ['rea_settings'],
            ['blog' => '1.0.0'],
            [],
        );
        $target = $this->database([]);
        (new BackupManager($target, $this->directory))->restoreIntoEmpty($path);

        self::assertSame($source->tables, $target->tables);
    }

    public function testTamperingAndNonEmptyTargetsAreRejected(): void
    {
        $source = $this->database(['rea_settings' => []]);
        $path = (new BackupManager($source, $this->directory))->create('core', ['rea_settings'], [], []);
        file_put_contents($path, str_replace('core', 'full', (string) file_get_contents($path)));
        $this->expectException(BackupException::class);
        (new BackupManager($this->database([]), $this->directory))->restoreIntoEmpty($path);
    }

    /** @param array<string, list<array<string, mixed>>> $tables */
    private function database(array $tables): BackupDatabase
    {
        return new class ($tables) implements BackupDatabase {
            /** @param array<string, list<array<string, mixed>>> $tables */
            public function __construct(public array $tables)
            {
            }
            public function export(array $tables): array
            {
                return array_intersect_key($this->tables, array_flip($tables));
            }
            public function isEmpty(): bool
            {
                return $this->tables === [];
            }
            public function restore(array $tables): void
            {
                $this->tables = $tables;
            }
        };
    }
}
