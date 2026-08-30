<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Blog;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Blog\BlogPost;
use ReaCms\Blog\BlogTransfer;

final class BlogTransferTest extends TestCase
{
    public function testExportImportValidationAndSitemapPreserveVersionedPublicData(): void
    {
        $post = new BlogPost(
            1,
            'Hello',
            'hello',
            'Excerpt',
            '<p>Body</p>',
            'published',
            'public',
            'en',
            new DateTimeImmutable('2026-08-29T12:00:00+00:00')
        );
        $transfer = new BlogTransfer();
        $json = $transfer->export([$post]);
        $validated = $transfer->validateImport($json);

        self::assertSame('Hello', $validated[0]['title']);
        self::assertStringContainsString(
            '<loc>https://example.test/blog/hello</loc>',
            $transfer->sitemap([$post], 'https://example.test')
        );
    }

    public function testImportRejectsUnknownFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new BlogTransfer())->validateImport(
            '{"schemaVersion":1,"plugin":{"id":"blog","version":"1.0.0"},"posts":[{"shell":"id"}]}',
        );
    }
}
