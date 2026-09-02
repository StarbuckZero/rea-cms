<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Podcast;

use PHPUnit\Framework\TestCase;
use ReaCms\Plugin\DeclarativeMigration;
use ReaCms\Plugin\ManifestValidator;

final class PodcastPackageTest extends TestCase
{
    public function testPodcastPackageOwnsNormalizedCacheTables(): void
    {
        $root = dirname(__DIR__, 3) . '/plugins/podcast';
        $manifestJson = file_get_contents($root . '/plugin.json');
        self::assertIsString($manifestJson);
        $manifest = (new ManifestValidator())->validate($manifestJson);
        $migration = file_get_contents($root . '/migrations/001_install.json');
        self::assertIsString($migration);
        $sql = (new DeclarativeMigration())->compile('podcast', $manifest, $migration);

        self::assertSame('podcast', $manifest->id);
        self::assertSame([
            'plugin_podcast_feeds',
            'plugin_podcast_episodes',
            'plugin_podcast_settings',
            'plugin_podcast_schedule_days',
        ], $manifest->tables);
        self::assertCount(8, $sql);
        self::assertStringContainsString('plugin_podcast_episodes', implode(' ', $sql));
        self::assertStringNotContainsString('rea_users', implode(' ', $sql));
        self::assertSame([], glob($root . '/**/*.php') ?: []);

        $scheduleMigration = file_get_contents($root . '/migrations/002_weekly_schedule.json');
        self::assertIsString($scheduleMigration);
        $scheduleSql = (new DeclarativeMigration())->compile('podcast', $manifest, $scheduleMigration);
        self::assertStringContainsString('schedule_timezone', implode(' ', $scheduleSql));
        self::assertStringContainsString('CREATE UNIQUE INDEX', implode(' ', $scheduleSql));
    }
}
