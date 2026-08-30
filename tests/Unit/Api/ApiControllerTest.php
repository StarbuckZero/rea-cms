<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use ReaCms\Api\ApiController;
use ReaCms\Api\Policy\NetworkMatcher;
use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Policy\PolicyEvaluator;
use ReaCms\Api\Query\ApiQuery;
use ReaCms\Api\RateLimit\RateLimitDecision;
use ReaCms\Api\RateLimit\RateLimiter;
use ReaCms\Core\Http\Request;
use ReaCms\Tests\Support\InMemoryAuthorization;

final class ApiControllerTest extends TestCase
{
    public function testAllFormatsUseTheSameAuthorizedQueryAndOnlyChangeSerialization(): void
    {
        $queries = 0;
        $controller = $this->controller(static function (ApiQuery $query) use (&$queries): array {
            $queries++;

            return ['data' => [['service' => 'rea-cms', 'status' => 'ok']]];
        });
        $request = new Request('GET', '/', ['origin' => 'http://rea-cms.test']);

        $json = $controller->status($request, 'json');
        $html = $controller->status($request, 'html');
        $text = $controller->status($request, 'txt');

        self::assertSame(3, $queries);
        self::assertSame('application/json; charset=UTF-8', $json->header('Content-Type'));
        self::assertSame('text/html; charset=UTF-8', $html->header('Content-Type'));
        self::assertSame('text/plain; charset=UTF-8', $text->header('Content-Type'));
        self::assertStringContainsString('rea-cms', $json->body());
        self::assertStringContainsString('rea-cms', $html->body());
        self::assertStringContainsString('rea-cms', $text->body());
        self::assertSame('http://rea-cms.test', $json->header('Access-Control-Allow-Origin'));
        self::assertSame('Origin', $json->header('Vary'));
    }

    public function testUnauthorizedOriginGetsNoPermissiveCorsHeader(): void
    {
        $controller = $this->controller(static fn (ApiQuery $query): array => ['data' => []]);
        $response = $controller->status(
            new Request('GET', '/', ['origin' => 'https://attacker.test']),
            'json',
        );

        self::assertSame(403, $response->status());
        self::assertNull($response->header('Access-Control-Allow-Origin'));
    }

    /** @param callable(ApiQuery): array<string, mixed> $query */
    private function controller(callable $query): ApiController
    {
        $origins = new OriginAllowlist(['http://rea-cms.test']);
        $rateLimiter = new class implements RateLimiter {
            public function consume(
                string $bucket,
                string $identity,
                int $maximum,
                int $windowSeconds,
            ): RateLimitDecision {
                return new RateLimitDecision(true, $maximum - 1, 0);
            }
        };

        return new ApiController(
            new PolicyEvaluator($origins, new NetworkMatcher(), new InMemoryAuthorization()),
            $origins,
            $rateLimiter,
            $query,
        );
    }
}
