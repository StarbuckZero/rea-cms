<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Podcast;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReaCms\Podcast\PodcastException;
use ReaCms\Podcast\PodcastUrlGuard;

final class PodcastUrlGuardTest extends TestCase
{
    /** @return list<array{string}> */
    public static function unsafeUrls(): array
    {
        return [
            ['file:///etc/passwd'],
            ['http://127.0.0.1/feed.xml'],
            ['http://10.0.0.1/feed.xml'],
            ['https://user:password@example.com/feed.xml'],
            ['https://example.com:8443/feed.xml'],
        ];
    }

    #[DataProvider('unsafeUrls')]
    public function testItRejectsUnsafeFeedDestinations(string $url): void
    {
        $this->expectException(PodcastException::class);
        (new PodcastUrlGuard())->validate($url);
    }
}
