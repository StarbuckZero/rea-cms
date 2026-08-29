<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Database\Migrations;

use PHPUnit\Framework\TestCase;
use ReaCms\Database\Migrations\CoreMigrationRunner;
use ReaCms\Tests\Support\InMemoryMigrationDatabase;
use RuntimeException;

final class CoreMigrationRunnerTest extends TestCase
{
    private string $migrationPath;

    protected function setUp(): void
    {
        $this->migrationPath = sys_get_temp_dir() . '/rea-cms-migrations-' . bin2hex(random_bytes(8));
        mkdir($this->migrationPath, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->migrationPath . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->migrationPath);
    }

    public function testItAppliesPendingMigrationsInOrderWithTheConfiguredPrefix(): void
    {
        file_put_contents($this->migrationPath . '/002_second.sql', 'CREATE TABLE `{{prefix}}second` (`id` INT);');
        file_put_contents($this->migrationPath . '/001_first.sql', 'CREATE TABLE `{{prefix}}first` (`id` INT);');
        $database = new InMemoryMigrationDatabase();
        $runner = new CoreMigrationRunner($database, $this->migrationPath, 'custom_');

        $migrated = $runner->migrate();

        self::assertSame(['001_first', '002_second'], $migrated);
        self::assertSame('custom_migrations', $database->trackingTable);
        self::assertStringContainsString('`custom_first`', $database->statements[0]);
        self::assertStringContainsString('`custom_second`', $database->statements[1]);
        self::assertCount(2, $database->records);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $database->records[0]['checksum']);
    }

    public function testItSkipsPreviouslyAppliedMigrations(): void
    {
        $sql = 'SELECT 1;';
        file_put_contents($this->migrationPath . '/001_first.sql', $sql);
        $database = new InMemoryMigrationDatabase(['001_first' => hash('sha256', $sql)]);
        $runner = new CoreMigrationRunner($database, $this->migrationPath);

        self::assertSame([], $runner->migrate());
        self::assertSame([], $database->statements);
    }

    public function testItRejectsChangesToAnAppliedMigration(): void
    {
        file_put_contents($this->migrationPath . '/001_first.sql', 'SELECT 2;');
        $database = new InMemoryMigrationDatabase(['001_first' => hash('sha256', 'SELECT 1;')]);
        $runner = new CoreMigrationRunner($database, $this->migrationPath);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no longer matches its recorded checksum');

        $runner->migrate();
    }

    public function testItRejectsUnsafeTablePrefixes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('table prefix is invalid');

        new CoreMigrationRunner(new InMemoryMigrationDatabase(), $this->migrationPath, 'rea_; DROP TABLE users');
    }
}
