<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Blog;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Template\PluginApiRenderer;
use ReaCms\Blog\BlogController;
use ReaCms\Blog\BlogPost;
use ReaCms\Blog\BlogRepository;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Routing\RouteNotFound;
use ReaCms\Plugin\PluginRecord;
use ReaCms\Plugin\PluginRouteGate;
use ReaCms\Tests\Support\FrozenClock;
use ReaCms\Tests\Support\InMemoryPluginRegistry;
use ReaCms\Tests\Support\InMemoryPluginApiTemplateRepository;

final class BlogControllerTest extends TestCase
{
    public function testDisabledBlogExposesNoRoute(): void
    {
        $this->expectException(RouteNotFound::class);
        $this->controller(false)->collection($this->request(), 'json');
    }

    public function testCollectionHasEquivalentJsonHtmlAndTextRepresentations(): void
    {
        $controller = $this->controller(true);
        foreach (['json', 'html', 'txt'] as $format) {
            $response = $controller->collection($this->request(), $format);
            self::assertSame(200, $response->status());
            self::assertStringContainsString('Published post', $response->body());
        }
    }

    private function controller(bool $enabled): BlogController
    {
        $repository = new class implements BlogRepository {
            public function published(string $locale, DateTimeImmutable $now, int $limit, int $offset): array
            {
                return [$this->post()];
            }
            public function countPublished(string $locale, DateTimeImmutable $now): int
            {
                return 1;
            }
            public function findPublishedById(int $id, string $locale, DateTimeImmutable $now): ?BlogPost
            {
                return $id === 1 ? $this->post() : null;
            }
            public function findPublishedBySlug(string $slug, string $locale, DateTimeImmutable $now): ?BlogPost
            {
                return $slug === 'published-post' ? $this->post() : null;
            }
            private function post(): BlogPost
            {
                return new BlogPost(
                    1,
                    'Published post',
                    'published-post',
                    'Excerpt',
                    '<p>Body</p>',
                    'published',
                    'public',
                    'en',
                    new DateTimeImmutable('2026-08-29T10:00:00+00:00'),
                );
            }
        };
        $registry = new InMemoryPluginRegistry();
        $registry->records['blog'] = new PluginRecord(
            'blog',
            '1.0.0',
            $enabled ? 'enabled' : 'disabled',
            str_repeat('a', 64),
        );
        return new BlogController(
            $repository,
            new PluginRouteGate($registry),
            new OriginAllowlist(['http://rea-cms.test']),
            new FrozenClock(new DateTimeImmutable('2026-08-29T12:00:00+00:00')),
            new PluginApiRenderer(new InMemoryPluginApiTemplateRepository()),
        );
    }

    private function request(): Request
    {
        return new Request('GET', '/api/v1/blog.json', ['origin' => 'http://rea-cms.test']);
    }
}
