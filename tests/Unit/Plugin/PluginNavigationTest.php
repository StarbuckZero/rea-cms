<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use ReaCms\Plugin\PluginNavigation;
use ReaCms\Plugin\PluginRecord;
use ReaCms\Tests\Support\InMemoryPluginAccess;
use ReaCms\Tests\Support\InMemoryPluginRegistry;

final class PluginNavigationTest extends TestCase
{
    public function testBuildsAnAlphabeticalMenuFromAccessiblePluginMetadata(): void
    {
        $plugins = new InMemoryPluginRegistry();
        $plugins->records['zebra'] = new PluginRecord(
            'zebra',
            '1.0.0',
            'enabled',
            'hash',
            'Zebra',
            '',
            'Zebra tools',
            '/cms/zebra',
        );
        $plugins->records['alpha'] = new PluginRecord(
            'alpha',
            '1.0.0',
            'enabled',
            'hash',
            'Alpha',
            '',
            null,
            '/cms/alpha',
        );
        $plugins->records['hidden'] = new PluginRecord(
            'hidden',
            '1.0.0',
            'enabled',
            'hash',
            'Hidden',
        );
        $plugins->records['denied'] = new PluginRecord(
            'denied',
            '1.0.0',
            'enabled',
            'hash',
            'Denied',
            '',
            'Denied',
            '/cms/denied',
        );
        $access = new InMemoryPluginAccess();
        $access->allowAll = false;
        $access->assignments[7] = ['zebra', 'alpha', 'hidden', 'media'];

        $items = (new PluginNavigation($plugins, $access))->forUser(7);

        self::assertSame(['alpha', 'media', 'zebra'], array_column($items, 'pluginId'));
        self::assertSame(['Alpha', 'Media', 'Zebra tools'], array_column($items, 'label'));
        self::assertSame(['/cms/alpha', '/cms/media', '/cms/zebra'], array_column($items, 'path'));
    }
}
