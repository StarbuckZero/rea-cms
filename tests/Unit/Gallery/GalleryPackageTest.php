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

    public function testGalleryDeclaresMixedMediaAndAlbumCapabilities(): void
    {
        $root = dirname(__DIR__, 3) . '/plugins/gallery';
        $manifestJson = file_get_contents($root . '/plugin.json');
        $migrationJson = file_get_contents($root . '/migrations/003_media_types.json');
        self::assertIsString($manifestJson);
        self::assertIsString($migrationJson);

        $manifest = (new ManifestValidator())->validate($manifestJson);
        $sql = implode(' ', (new DeclarativeMigration())->compile('gallery', $manifest, $migrationJson));

        self::assertSame('1.1.0', $manifest->version);
        self::assertContains('gallery.items.create', $manifest->permissions);
        self::assertContains('gallery.albums.delete', $manifest->permissions);
        self::assertStringContainsString('`media_type` VARCHAR(16) NULL', $sql);
        self::assertStringContainsString('gallery_item_status_index', $sql);
    }

    public function testGalleryRoutesAndAdminViewsCoverItemsAndAlbums(): void
    {
        $root = dirname(__DIR__, 3);
        $factory = file_get_contents($root . '/app/Core/Http/ApplicationFactory.php');
        $itemEditor = file_get_contents($root . '/resources/views/cms/gallery/editor.php');
        $albumEditor = file_get_contents($root . '/resources/views/cms/gallery/album-editor.php');
        self::assertIsString($factory);
        self::assertIsString($itemEditor);
        self::assertIsString($albumEditor);

        self::assertStringContainsString('/api/v1/gallery/{id}.{format}', $factory);
        self::assertStringContainsString('/api/v1/gallery/albums.{format}', $factory);
        self::assertStringContainsString('/api/v1/gallery/albums/{id}/items.{format}', $factory);
        self::assertStringContainsString('name="album_id"', $itemEditor);
        self::assertStringContainsString('name="cover_media_id"', $albumEditor);
        self::assertStringContainsString('/reorder', $albumEditor);
        self::assertFileExists($root . '/public/assets/gallery-default-album-cover.svg');
    }
}
