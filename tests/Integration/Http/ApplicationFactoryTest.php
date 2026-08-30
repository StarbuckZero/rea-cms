<?php

declare(strict_types=1);

namespace ReaCms\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use ReaCms\Core\Configuration\Environment;
use ReaCms\Core\Http\ApplicationFactory;
use ReaCms\Core\Http\Request;

final class ApplicationFactoryTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 3);
    }

    public function testHomeRendersThroughTheConfiguredApplication(): void
    {
        $application = ApplicationFactory::create($this->environment(), $this->projectRoot);
        $response = $application->handle(new Request(
            'GET',
            '/',
            ['cookie' => 'rea_theme=dark'],
        ));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('<html lang="en" data-theme="dark">', $response->body());
        self::assertStringContainsString('hx-get="/fragments/welcome"', $response->body());
        self::assertStringContainsString('<a class="button-primary" href="/login">Login</a>', $response->body());
        self::assertStringNotContainsString('class="profile-menu"', $response->body());
        self::assertStringNotContainsString('data-theme-choice=', $response->body());
    }

    public function testFragmentReturnsPresentationOnlyHtml(): void
    {
        $application = ApplicationFactory::create($this->environment(), $this->projectRoot);
        $response = $application->handle(new Request('GET', '/fragments/welcome'));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('server-rendered fragment', $response->body());
        self::assertStringNotContainsString('<html', $response->body());
    }

    public function testHealthEndpointExposesOnlyStatus(): void
    {
        $application = ApplicationFactory::create($this->environment(), $this->projectRoot);
        $response = $application->handle(new Request('GET', '/health'));

        self::assertSame(200, $response->status());
        self::assertSame('{"status":"ok"}', $response->body());
    }

    private function environment(): Environment
    {
        return Environment::fromArray([
            'APP_DEBUG' => 'false',
            'LOG_LEVEL' => 'emergency',
        ]);
    }
}
