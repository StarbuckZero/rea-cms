<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\TextBlock;

use PHPUnit\Framework\TestCase;
use ReaCms\Api\Template\PluginApiFieldCatalog;
use ReaCms\Plugin\DeclarativeMigration;
use ReaCms\Plugin\ManifestValidator;
use ReaCms\Plugin\PluginRecord;

final class TextBlockPackageTest extends TestCase
{
    public function testPackageDeclaresStoragePermissionsApiAndTemplateFields(): void
    {
        $root = dirname(__DIR__, 3);
        $pluginRoot = $root . '/plugins/text_block';
        $manifestJson = file_get_contents($pluginRoot . '/plugin.json');
        $migrationJson = file_get_contents($pluginRoot . '/migrations/001_install.json');
        self::assertIsString($manifestJson);
        self::assertIsString($migrationJson);

        $manifest = (new ManifestValidator())->validate($manifestJson);
        $sql = implode(' ', (new DeclarativeMigration())->compile('text_block', $manifest, $migrationJson));
        $fields = (new PluginApiFieldCatalog($root . '/plugins'))->fields(new PluginRecord(
            'text_block',
            '1.0.0',
            'enabled',
            str_repeat('a', 64),
        ));

        self::assertSame(['plugin_text_block_blocks'], $manifest->tables);
        self::assertSame('text-block', $manifest->document['api']['resource']);
        self::assertSame('textBlock', $manifest->document['api']['binding']);
        self::assertContains('text_block.blocks.delete', $manifest->permissions);
        self::assertStringContainsString('CREATE UNIQUE INDEX `text_block_name_unique`', $sql);
        self::assertStringContainsString('`created_at` TIMESTAMP(6)', $sql);
        self::assertContains('{textBlock.content}', array_column($fields, 'binding'));
        self::assertStringContainsString(
            '{textBlock.content | sanitized_html}',
            (string) file_get_contents($pluginRoot . '/templates/api/detail.html'),
        );
    }

    public function testRoutesAndManagementViewsCoverTheRequestedWorkflow(): void
    {
        $root = dirname(__DIR__, 3);
        $factory = file_get_contents($root . '/app/Core/Http/ApplicationFactory.php');
        $index = file_get_contents($root . '/resources/views/cms/text-block/index.php');
        self::assertIsString($factory);
        self::assertIsString($index);

        self::assertStringContainsString('/api/v1/text-block/name/{name}.{format}', $factory);
        self::assertStringContainsString('/api/v1/text-block/{id}.{format}', $factory);
        self::assertStringContainsString('/cms/text-block/{id}/delete', $factory);
        self::assertStringContainsString('name="q"', $index);
        self::assertStringContainsString('data-copy-value', $index);
        self::assertStringContainsString('data-confirm-delete', $index);
        self::assertStringContainsString('Last updated', $index);
    }
}
