<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Core\Routing;

use PHPUnit\Framework\TestCase;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Routing\MethodNotAllowed;
use ReaCms\Core\Routing\RouteNotFound;
use ReaCms\Core\Routing\Router;

final class RouterTest extends TestCase
{
    public function testItDispatchesStaticRoutes(): void
    {
        $router = new Router();
        $router->get('/health', static fn (): Response => Response::json(['status' => 'ok']));

        $response = $router->dispatch(new Request('GET', '/health'));

        self::assertSame(200, $response->status());
        self::assertSame('{"status":"ok"}', $response->body());
    }

    public function testItExtractsRouteParameters(): void
    {
        $router = new Router();
        $router->get('/posts/{id}', static fn (Request $request, array $parameters): Response => (
            Response::html($parameters['id'])
        ));

        $response = $router->dispatch(new Request('GET', '/posts/42?preview=1'));

        self::assertSame('42', $response->body());
    }

    public function testUnknownRoutesThrow(): void
    {
        $this->expectException(RouteNotFound::class);

        (new Router())->dispatch(new Request('GET', '/missing'));
    }

    public function testWrongMethodsReportAllowedMethods(): void
    {
        $router = new Router();
        $router->get('/health', static fn (): Response => Response::html('ok'));

        try {
            $router->dispatch(new Request('POST', '/health'));
            self::fail('Expected a method-not-allowed exception.');
        } catch (MethodNotAllowed $exception) {
            self::assertSame(['GET'], $exception->allowedMethods());
        }
    }
}
