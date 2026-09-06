<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Setup;

use PHPUnit\Framework\TestCase;
use ReaCms\Core\Http\Request;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Setup\EnvironmentFile;
use ReaCms\Setup\InstallController;
use ReaCms\Setup\Installer;
use ReaCms\Setup\InstallState;
use ReaCms\Setup\PlatformInspector;
use ReaCms\Setup\SetupTokenStore;

final class InstallControllerTest extends TestCase
{
    private string $directory;
    private InstallController $controller;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/rea-cms-install-controller-' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/storage', 0700, true);
        $environment = new EnvironmentFile($this->directory);
        $this->controller = new InstallController(
            new ViewRenderer(dirname(__DIR__, 3) . '/resources/views'),
            new PlatformInspector($this->directory),
            $environment,
            new SetupTokenStore($this->directory),
            new Installer($this->directory, $environment, new InstallState($this->directory)),
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/storage/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->directory . '/.*') ?: [] as $file) {
            if (basename($file) !== '.' && basename($file) !== '..') {
                unlink($file);
            }
        }
        rmdir($this->directory . '/storage');
        rmdir($this->directory);
    }

    public function testItRendersAProtectedFirstRunFormWithoutSecrets(): void
    {
        $response = $this->controller->form(new Request('GET', '/install'));

        self::assertSame(200, $response->status());
        self::assertSame('no-store, private', $response->header('Cache-Control'));
        self::assertStringContainsString('Install Rea CMS', $response->body());
        self::assertStringContainsString('name="_csrf"', $response->body());
        self::assertStringContainsString('name="db_password"', $response->body());
        self::assertStringNotContainsString('replace-with', $response->body());
    }

    public function testItRedirectsToLoginWhenConfigurationAlreadyExists(): void
    {
        file_put_contents($this->directory . '/.env', "APP_ENV=production\n");

        $response = $this->controller->form(new Request('GET', '/install'));

        self::assertSame(303, $response->status());
        self::assertSame('/login', $response->header('Location'));
    }

    public function testValidationErrorsDoNotRedisplaySubmittedPasswords(): void
    {
        $formResponse = $this->controller->form(new Request('GET', '/install'));
        self::assertSame(1, preg_match('/name="_csrf" value="([a-f0-9]{64})"/', $formResponse->body(), $matches));
        $databasePassword = 'unique database secret';
        $adminPassword = 'unique administrator secret';
        $body = http_build_query([
            '_csrf' => $matches[1],
            'app_url' => 'http://insecure.example.com',
            'timezone' => 'UTC',
            'mail_from' => 'no-reply@example.com',
            'db_host' => 'localhost',
            'db_port' => '3306',
            'db_database' => 'cms',
            'db_username' => 'cms',
            'db_password' => $databasePassword,
            'db_table_prefix' => 'rea_',
            'admin_email' => 'admin@example.com',
            'admin_name' => 'Administrator',
            'admin_password' => $adminPassword,
            'admin_password_confirmation' => $adminPassword,
        ]);

        $response = $this->controller->install(new Request('POST', '/install', [], [], $body));

        self::assertSame(422, $response->status());
        self::assertStringNotContainsString($databasePassword, $response->body());
        self::assertStringNotContainsString($adminPassword, $response->body());
    }
}
