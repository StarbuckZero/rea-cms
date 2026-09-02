<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use ReaCms\Plugin\DeclarativeMigration;
use ReaCms\Plugin\ManifestValidator;
use ReaCms\Plugin\PluginException;

final class DeclarativeMigrationTest extends TestCase
{
    public function testAllowlistedMigrationCompilesOnlyDeclaredTable(): void
    {
        $manifest = $this->manifest();
        $sql = (new DeclarativeMigration())->compile('notes', $manifest, json_encode([
            'operations' => [[
                'action' => 'create_table',
                'table' => 'plugin_notes_entries',
                'columns' => [
                    ['name' => 'id', 'type' => 'bigint', 'primary' => true, 'autoIncrement' => true],
                    ['name' => 'title', 'type' => 'varchar', 'length' => 255],
                ],
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertCount(1, $sql);
        self::assertStringContainsString('`plugin_notes_entries`', $sql[0]);
        self::assertStringNotContainsString('rea_users', $sql[0]);
    }

    public function testNamespaceEscapeAndRawSqlAreRejected(): void
    {
        $this->expectException(PluginException::class);
        (new DeclarativeMigration())->compile('notes', $this->manifest(), json_encode([
            'operations' => [['action' => 'sql', 'table' => 'rea_users', 'sql' => 'DROP TABLE rea_users']],
        ], JSON_THROW_ON_ERROR));
    }

    public function testUniqueIndexesCanEnforcePluginOwnedNaturalKeys(): void
    {
        $sql = (new DeclarativeMigration())->compile('notes', $this->manifest(), json_encode([
            'operations' => [[
                'action' => 'create_index',
                'table' => 'plugin_notes_entries',
                'name' => 'notes_title_unique',
                'columns' => ['title'],
                'unique' => true,
            ]],
        ], JSON_THROW_ON_ERROR));

        self::assertSame(
            'CREATE UNIQUE INDEX `notes_title_unique` ON `plugin_notes_entries` (`title`)',
            $sql[0],
        );
    }

    private function manifest(): \ReaCms\Plugin\Manifest
    {
        return (new ManifestValidator())->validate(json_encode([
            'schemaVersion' => 1, 'id' => 'notes', 'name' => 'Notes', 'version' => '1.0.0',
            'reaCmsVersion' => '^1.0', 'description' => '', 'tables' => ['plugin_notes_entries'],
            'permissions' => [],
        ], JSON_THROW_ON_ERROR));
    }
}
