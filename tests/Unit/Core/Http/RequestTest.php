<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Core\Http;

use PHPUnit\Framework\TestCase;
use ReaCms\Core\Http\Request;

final class RequestTest extends TestCase
{
    public function testItNormalizesMethodAndPath(): void
    {
        $request = new Request('get', '/posts/hello%20world?page=2');

        self::assertSame('GET', $request->method());
        self::assertSame('/posts/hello world', $request->path());
    }

    public function testItReadsExactCookieNames(): void
    {
        $request = new Request('GET', '/', ['cookie' => 'other=x; rea_theme=high-contrast']);

        self::assertSame('high-contrast', $request->cookie('rea_theme'));
        self::assertNull($request->cookie('theme'));
    }

    public function testApiPathsAndAcceptHeadersRequestJson(): void
    {
        self::assertTrue((new Request('GET', '/api/v1/posts.json'))->expectsJson());
        self::assertTrue((new Request('GET', '/', ['accept' => 'application/json']))->expectsJson());
        self::assertFalse((new Request('GET', '/', ['accept' => 'text/html']))->expectsJson());
    }
}
