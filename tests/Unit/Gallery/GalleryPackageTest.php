<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Gallery;

use PHPUnit\Framework\TestCase;
use ReaCms\Plugin\DeclarativeMigration;
use ReaCms\Plugin\ManifestValidator;

final class GalleryPackageTest extends TestCase
{
    public function testGalleryUsesLogicalCoreMediaIdsWithoutOwningMediaFiles(): void
    {
        $root = dirname(__DIR__, 3) . '/plugins/gallery';
        $json = file_get_contents($root . '/plugin.json');
        $migration = file_get_contents($root . '/migrations/001_install.json');
        self::assertIsString($json);
        self::assertIsString($migration);
        $manifest = (new ManifestValidator())->validate($json);
        $sql = implode(' ', (new DeclarativeMigration())->compile('gallery', $manifest, $migration));

        self::assertStringContainsString('`media_id` BIGINT UNSIGNED', $sql);
        self::assertStringNotContainsString('stored_name', $sql);
        self::assertStringNotContainsString('file_hash', $sql);
        self::assertSame([], glob($root . '/**/*.php') ?: []);
    }
}
