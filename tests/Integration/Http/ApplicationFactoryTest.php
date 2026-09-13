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

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testEventsZipLoadsNewClassesWithoutComposerDiscovery(): void
    {
        $rejectEvents = static function (string $class): void {
            if (str_starts_with($class, 'ReaCms\\Events\\')) {
                throw new \RuntimeException('The installed Composer map cannot resolve Events classes.');
            }
        };
        spl_autoload_register($rejectEvents, true, true);
        try {
            $application = ApplicationFactory::create($this->environment(), $this->projectRoot);
            // No database is configured; verify class loading before connection setup fails.
            $application->handle(new Request('GET', '/cms/events'));
            foreach (
                [
                'EventDates', 'EventValidator', 'EventQuery', 'EventPresenter', 'EventCalendar',
                'PdoEventRepository', 'EventController', 'EventControllerFactory',
                ] as $class
            ) {
                self::assertTrue(class_exists('ReaCms\\Events\\' . $class, false), $class);
            }
        } finally {
            spl_autoload_unregister($rejectEvents);
        }
    }

    private function environment(): Environment
    {
        return Environment::fromArray([
            'APP_DEBUG' => 'false',
            'LOG_LEVEL' => 'emergency',
        ]);
    }
}
