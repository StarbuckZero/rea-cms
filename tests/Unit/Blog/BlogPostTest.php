<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Blog;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Blog\BlogPost;

final class BlogPostTest extends TestCase
{
    public function testMissingPublicationHasEmptyDisplayFields(): void
    {
        $data = $this->post(null)->api('America/New_York');
        self::assertNull($data['publishedAt']);
        self::assertSame('', $data['publishedDate']);
        self::assertSame('', $data['publishedTime']);
    }

    public function testTimezoneRolloverDaylightSavingAndFallback(): void
    {
        foreach (['2026-09-09T00:30:00Z' => '8:30 PM', '2026-01-09T00:30:00Z' => '7:30 PM'] as $iso => $time) {
            $post = $this->post(new DateTimeImmutable($iso));
            $data = $post->api('America/New_York');
            self::assertSame($time, $data['publishedTime']);
            self::assertStringContainsString('8, 2026', $data['publishedDate']);
            foreach ([null, '', 'Invalid/Zone'] as $zone) {
                self::assertSame('12:30 AM', $post->api($zone)['publishedTime']);
            }
        }
    }

    private function post(?DateTimeImmutable $date): BlogPost
    {
        return new BlogPost(1, 'Title', 'title', '', '', 'published', 'public', 'en', $date);
    }
}
