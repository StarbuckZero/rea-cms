<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Api\Template;

use PHPUnit\Framework\TestCase;
use ReaCms\Api\Template\PluginApiFieldCatalog;
use ReaCms\Plugin\PluginRecord;

final class PluginApiFieldCatalogTest extends TestCase
{
    public function testFieldsComeFromTheCurrentPluginManifestMetadata(): void
    {
        $catalog = new PluginApiFieldCatalog(dirname(__DIR__, 4) . '/plugins');
        $fields = $catalog->fields(new PluginRecord('blog', '1.0.0', 'enabled', str_repeat('a', 64)));
        $bindings = array_column($fields, 'binding');

        self::assertContains('{blog.id}', $bindings);
        self::assertContains('{blog.title}', $bindings);
        self::assertContains('{blog.content}', $bindings);
        $content = $fields[array_search('{blog.content}', $bindings, true)];
        self::assertSame('Content', $content['label']);
        self::assertSame('html', $content['type']);
        self::assertStringContainsString('rich-text', $content['description']);
    }

    public function testSampleDataSupportsNestedPluginFields(): void
    {
        $catalog = new PluginApiFieldCatalog(dirname(__DIR__, 4) . '/plugins');
        $sample = $catalog->sample(new PluginRecord('podcast', '1.0.0', 'enabled', str_repeat('a', 64)));

        self::assertSame(123, $sample['feed']['id']);
        self::assertSame('Sample feed title', $sample['feed']['title']);
        self::assertSame('https://example.com/audio/url', $sample['audio']['url']);
        self::assertStringContainsString('<p>Sample content.', $sample['content']);
    }
}
