<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Blog;

use PHPUnit\Framework\TestCase;
use ReaCms\Plugin\DeclarativeMigration;
use ReaCms\Plugin\ManifestValidator;

final class BlogPackageTest extends TestCase
{
    public function testReferencePackageUsesOnlyDeclarativePublicCapabilities(): void
    {
        $root = dirname(__DIR__, 3) . '/plugins/blog';
        $manifestJson = file_get_contents($root . '/plugin.json');
        self::assertIsString($manifestJson);
        $manifest = (new ManifestValidator())->validate($manifestJson);
        $migration = file_get_contents($root . '/migrations/001_install.json');
        self::assertIsString($migration);
        $sql = (new DeclarativeMigration())->compile('blog', $manifest, $migration);

        self::assertSame('blog', $manifest->id);
        self::assertCount(6, $manifest->permissions);
        self::assertCount(6, $sql);
        self::assertStringContainsString('plugin_blog_posts', implode(' ', $sql));
        self::assertStringNotContainsString('rea_users', implode(' ', $sql));
        self::assertSame([], glob($root . '/**/*.php') ?: []);
    }
}
