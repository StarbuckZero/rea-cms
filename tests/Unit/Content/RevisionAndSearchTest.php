<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Content;

use PHPUnit\Framework\TestCase;
use ReaCms\Content\ContentSearch;
use ReaCms\Content\ResourceDefinition;
use ReaCms\Content\RevisionService;
use ReaCms\Content\SearchProvider;
use ReaCms\Plugin\PluginRecord;
use ReaCms\Plugin\PluginRouteGate;
use ReaCms\Tests\Support\InMemoryPluginRegistry;

final class RevisionAndSearchTest extends TestCase
{
    public function testRestoreSavesCurrentRevisionBeforeReplacingIt(): void
    {
        $events = [];
        (new RevisionService())->restore(
            $this->definition(),
            ['title' => 'Current'],
            ['title' => 'Old'],
            static function (array $snapshot) use (&$events): void {
                $events[] = ['saved', $snapshot];
            },
            static function (array $snapshot) use (&$events): void {
                $events[] = ['restored', $snapshot];
            },
        );

        self::assertSame([
            ['saved', ['title' => 'Current']],
            ['restored', ['title' => 'Old']],
        ], $events);
    }

    public function testSearchRequiresEnabledPluginAndFiltersNonPublicResults(): void
    {
        $provider = new class implements SearchProvider {
            public function search(string $pluginId, string $resource, string $query, string $locale, int $limit): array
            {
                return [
                    ['id' => 1, 'status' => 'published', 'visibility' => 'public', 'locale' => 'en'],
                    ['id' => 2, 'status' => 'draft', 'visibility' => 'public', 'locale' => 'en'],
                    ['id' => 3, 'status' => 'published', 'visibility' => 'private', 'locale' => 'en'],
                ];
            }
        };
        $registry = new InMemoryPluginRegistry();
        $search = new ContentSearch($provider, new PluginRouteGate($registry));
        self::assertSame([], $search->publicSearch('blog', 'posts', 'hello', 'en'));

        $registry->records['blog'] = new PluginRecord('blog', '1.0.0', 'enabled', str_repeat('a', 64));
        self::assertSame([1], array_column($search->publicSearch('blog', 'posts', 'hello', 'en'), 'id'));
    }

    private function definition(): ResourceDefinition
    {
        return new ResourceDefinition('blog', 'posts', 'plugin_blog_posts', ['title' => 'string'], ['title'], []);
    }
}
