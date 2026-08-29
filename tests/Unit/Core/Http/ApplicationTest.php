<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Core\Http;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReaCms\Core\Error\ErrorHandler;
use ReaCms\Core\Http\Application;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Http\SecurityHeaders;
use ReaCms\Core\Routing\Router;
use ReaCms\Core\View\ViewRenderer;
use RuntimeException;

final class ApplicationTest extends TestCase
{
    private ViewRenderer $views;

    protected function setUp(): void
    {
        $this->views = new ViewRenderer(dirname(__DIR__, 4) . '/resources/views');
    }

    public function testItAddsRequestIdAndSecurityHeaders(): void
    {
        $router = new Router();
        $router->get('/', static fn (): Response => Response::html('ok'));
        $application = $this->application($router);

        $response = $application->handle(new Request('GET', '/'));

        self::assertSame('test-request-id', $response->header('X-Request-ID'));
        self::assertSame('nosniff', $response->header('X-Content-Type-Options'));
        self::assertSame('DENY', $response->header('X-Frame-Options'));
        self::assertStringContainsString("frame-ancestors 'none'", $response->header('Content-Security-Policy') ?? '');
    }

    public function testUnknownApiRoutesReturnTheJsonErrorEnvelope(): void
    {
        $response = $this->application(new Router())->handle(new Request('GET', '/api/v1/missing.json'));

        self::assertSame(404, $response->status());
        self::assertSame('application/json; charset=UTF-8', $response->header('Content-Type'));
        self::assertSame([
            'error' => [
                'code' => 'not_found',
                'message' => 'The requested page could not be found.',
            ],
            'requestId' => 'test-request-id',
        ], json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testProductionErrorsDoNotExposeExceptionDetails(): void
    {
        $router = new Router();
        $router->get('/failure', static function (): never {
            throw new RuntimeException('secret path /srv/private and SQL SELECT password');
        });

        $response = $this->application($router)->handle(new Request(
            'GET',
            '/failure',
            ['accept' => 'application/json'],
        ));

        self::assertSame(500, $response->status());
        self::assertStringNotContainsString('/srv/private', $response->body());
        self::assertStringNotContainsString('SELECT password', $response->body());
        self::assertStringNotContainsString(RuntimeException::class, $response->body());
    }

    public function testHtmlNotFoundResponseIsSafe(): void
    {
        $response = $this->application(new Router())->handle(new Request('GET', '/missing'));

        self::assertSame(404, $response->status());
        self::assertStringContainsString('The requested page could not be found.', $response->body());
        self::assertStringContainsString('test-request-id', $response->body());
    }

    private function application(Router $router): Application
    {
        return new Application(
            $router,
            new ErrorHandler(new NullLogger(), $this->views, false),
            new SecurityHeaders(),
            static fn (): string => 'test-request-id',
        );
    }
}
