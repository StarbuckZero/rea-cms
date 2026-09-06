<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Operations;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Auth\AuthServices;
use ReaCms\Auth\LoginService;
use ReaCms\Auth\PasswordHasher;
use ReaCms\Auth\PasswordResetService;
use ReaCms\Auth\SessionManager;
use ReaCms\Auth\User;
use ReaCms\Core\Http\Request;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Database\Migrations\CoreMigrationRunner;
use ReaCms\Operations\UpgradeController;
use ReaCms\Security\Csrf;
use ReaCms\Tests\Support\CapturingPasswordResetDelivery;
use ReaCms\Tests\Support\FrozenClock;
use ReaCms\Tests\Support\InMemoryAuditLogger;
use ReaCms\Tests\Support\InMemoryAuthorization;
use ReaCms\Tests\Support\InMemoryLoginThrottle;
use ReaCms\Tests\Support\InMemoryMigrationDatabase;
use ReaCms\Tests\Support\InMemoryPasswordResetRepository;
use ReaCms\Tests\Support\InMemoryPluginAccess;
use ReaCms\Tests\Support\InMemoryPluginRegistry;
use ReaCms\Tests\Support\InMemoryRoleLookup;
use ReaCms\Tests\Support\InMemorySessionRepository;
use ReaCms\Tests\Support\InMemoryUserRepository;

final class UpgradeControllerTest extends TestCase
{
    private string $directory;
    private InMemoryUserRepository $users;
    private InMemorySessionRepository $sessionRepository;
    private SessionManager $sessions;
    private InMemoryAuthorization $authorization;
    private InMemoryRoleLookup $roles;
    private InMemoryAuditLogger $audit;
    private InMemoryMigrationDatabase $database;
    private PasswordHasher $passwords;
    private Csrf $csrf;
    private UpgradeController $controller;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/rea-cms-upgrade-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        file_put_contents($this->directory . '/008_next.sql', 'SELECT 8;');

        $clock = new FrozenClock(new DateTimeImmutable('2026-09-03T12:00:00+00:00'));
        $this->users = new InMemoryUserRepository();
        $this->sessionRepository = new InMemorySessionRepository();
        $this->sessions = new SessionManager($this->sessionRepository, $clock, 120, false);
        $this->authorization = new InMemoryAuthorization();
        $this->roles = new InMemoryRoleLookup();
        $this->audit = new InMemoryAuditLogger();
        $this->database = new InMemoryMigrationDatabase();
        $this->passwords = new PasswordHasher();
        $this->csrf = new Csrf(str_repeat('u', 64));
        $plugins = new InMemoryPluginRegistry();
        $pluginAccess = new InMemoryPluginAccess();
        $services = new AuthServices(
            $this->users,
            $this->sessionRepository,
            $this->sessions,
            new LoginService($this->users, new InMemoryLoginThrottle(), $this->passwords, $clock),
            $this->authorization,
            $this->audit,
            $this->csrf,
            new PasswordResetService(
                $this->users,
                new InMemoryPasswordResetRepository(),
                $this->sessionRepository,
                $this->passwords,
                new CapturingPasswordResetDelivery(),
                $clock,
                'https://cms.example.com',
            ),
            $plugins,
            $pluginAccess,
        );
        $this->controller = new UpgradeController(
            $services,
            $this->roles,
            new ViewRenderer(dirname(__DIR__, 3) . '/resources/views'),
            new CoreMigrationRunner($this->database, $this->directory),
            '1.2.3',
            $this->directory . '/upgrade.lock',
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testUpgradePageRequiresASuperAdministrator(): void
    {
        $request = $this->authenticatedRequest('GET', '/admin/upgrade');

        self::assertSame(403, $this->controller->index($request)->status());

        $this->roles->roles[1] = ['super-administrator'];
        $response = $this->controller->index($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Upgrade Rea CMS', $response->body());
        self::assertStringContainsString('008_next', $response->body());
    }

    public function testUpgradeRequiresBackupPasswordAndCsrfBeforeApplyingMigrations(): void
    {
        $this->roles->roles[1] = ['super-administrator'];
        $request = $this->authenticatedRequest('POST', '/admin/upgrade');
        $sessionToken = $request->cookie(SessionManager::COOKIE) ?? '';
        $body = http_build_query([
            '_csrf' => $this->csrf->token($sessionToken),
            'backup_confirmed' => '1',
            'password' => 'correct horse battery staple',
        ]);
        $response = $this->controller->apply(new Request(
            'POST',
            '/admin/upgrade',
            ['cookie' => SessionManager::COOKIE . '=' . $sessionToken],
            [],
            $body,
        ));

        self::assertSame(303, $response->status());
        self::assertSame('/admin/upgrade?upgraded=1', $response->header('Location'));
        self::assertSame('008_next', $this->database->records[0]['version']);
        self::assertSame('system.upgrade_completed', $this->audit->events[0]['event']);
    }

    private function authenticatedRequest(string $method, string $path): Request
    {
        $this->users->users[1] = new User(
            1,
            'admin@example.com',
            $this->passwords->hash('correct horse battery staple'),
            'active',
            'Administrator',
        );
        $this->authorization->permissions[1] = ['core.admin.access', 'core.plugins.view'];
        $anonymous = $this->sessions->start(new Request('GET', '/login'));
        $authenticated = $this->sessions->rotate(new Request('POST', '/login'), $anonymous, 1);

        return new Request(
            $method,
            $path,
            ['cookie' => SessionManager::COOKIE . '=' . $authenticated->token],
        );
    }
}
