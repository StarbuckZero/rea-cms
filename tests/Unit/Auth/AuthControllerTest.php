<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Auth;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Auth\AuthController;
use ReaCms\Auth\AuthServices;
use ReaCms\Auth\LoginService;
use ReaCms\Auth\PasswordHasher;
use ReaCms\Auth\PasswordResetService;
use ReaCms\Auth\SessionManager;
use ReaCms\Auth\User;
use ReaCms\Core\Http\Request;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Security\Csrf;
use ReaCms\Tests\Support\CapturingPasswordResetDelivery;
use ReaCms\Tests\Support\FrozenClock;
use ReaCms\Tests\Support\InMemoryAuditLogger;
use ReaCms\Tests\Support\InMemoryAuthorization;
use ReaCms\Tests\Support\InMemoryLoginThrottle;
use ReaCms\Tests\Support\InMemoryPasswordResetRepository;
use ReaCms\Tests\Support\InMemorySessionRepository;
use ReaCms\Tests\Support\InMemoryUserRepository;
use ReaCms\Tests\Support\InMemoryPluginRegistry;
use ReaCms\Tests\Support\InMemoryPluginAccess;
use ReaCms\Plugin\PluginRecord;

final class AuthControllerTest extends TestCase
{
    private InMemoryUserRepository $users;
    private InMemorySessionRepository $sessionRepository;
    private SessionManager $sessions;
    private InMemoryAuthorization $authorization;
    private InMemoryAuditLogger $audit;
    private Csrf $csrf;
    private AuthController $controller;
    private PasswordHasher $passwords;
    private InMemoryPluginRegistry $plugins;
    private InMemoryPluginAccess $pluginAccess;

    protected function setUp(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-08-29T12:00:00+00:00'));
        $this->users = new InMemoryUserRepository();
        $this->sessionRepository = new InMemorySessionRepository();
        $this->sessions = new SessionManager($this->sessionRepository, $clock, 120, false);
        $this->authorization = new InMemoryAuthorization();
        $this->audit = new InMemoryAuditLogger();
        $this->csrf = new Csrf(str_repeat('k', 64));
        $this->passwords = new PasswordHasher();
        $resets = new InMemoryPasswordResetRepository();
        $resetService = new PasswordResetService(
            $this->users,
            $resets,
            $this->sessionRepository,
            $this->passwords,
            new CapturingPasswordResetDelivery(),
            $clock,
            'https://cms.example.com',
        );
        $this->plugins = new InMemoryPluginRegistry();
        $this->pluginAccess = new InMemoryPluginAccess();
        $services = new AuthServices(
            $this->users,
            $this->sessionRepository,
            $this->sessions,
            new LoginService(
                $this->users,
                new InMemoryLoginThrottle(),
                $this->passwords,
                $clock,
            ),
            $this->authorization,
            $this->audit,
            $this->csrf,
            $resetService,
            $this->plugins,
            $this->pluginAccess,
        );
        $this->controller = new AuthController(
            $services,
            new ViewRenderer(dirname(__DIR__, 3) . '/resources/views'),
        );
    }

    public function testLoginFormCreatesAnAnonymousCsrfSession(): void
    {
        $response = $this->controller->loginForm(new Request('GET', '/login'));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('rea_session=', $response->header('Set-Cookie') ?? '');
        self::assertStringContainsString('name="_csrf"', $response->body());
        self::assertStringNotContainsString('replace-with', $response->body());
        self::assertStringContainsString('href="/login">Login</a>', $response->body());
        self::assertStringNotContainsString('class="profile-menu"', $response->body());
    }

    public function testLoginRejectsMissingCsrfTokens(): void
    {
        $response = $this->controller->login(new Request(
            'POST',
            '/login',
            [],
            [],
            'email=user%40example.com&password=password',
        ));

        self::assertSame(419, $response->status());
        self::assertSame('auth.csrf_failed', $this->audit->events[0]['event']);
    }

    public function testSuccessfulLoginRotatesTheSessionIdentifier(): void
    {
        $this->users->users[1] = new User(
            1,
            'admin@example.com',
            $this->passwords->hash('correct horse battery staple'),
            'active',
            'Admin',
        );
        $initial = $this->sessions->start(new Request('GET', '/login'));
        $body = http_build_query([
            '_csrf' => $this->csrf->token($initial->token),
            'email' => 'admin@example.com',
            'password' => 'correct horse battery staple',
        ]);
        $response = $this->controller->login(new Request(
            'POST',
            '/login',
            ['cookie' => 'rea_session=' . $initial->token],
            [],
            $body,
        ));

        self::assertSame(303, $response->status());
        self::assertSame('/dashboard', $response->header('Location'));
        self::assertStringNotContainsString($initial->token, $response->header('Set-Cookie') ?? '');
        self::assertContains($initial->record->tokenHash, $this->sessionRepository->revoked);
        self::assertSame('auth.login_succeeded', $this->audit->events[0]['event']);
    }

    public function testAdminRequiresAuthenticationAndPermission(): void
    {
        $anonymousResponse = $this->controller->admin(new Request('GET', '/admin'));
        self::assertSame('/login', $anonymousResponse->header('Location'));

        $this->users->users[1] = new User(1, 'user@example.com', 'hash', 'active', 'User');
        $anonymous = $this->sessions->start(new Request('GET', '/login'));
        $authenticated = $this->sessions->rotate(new Request('POST', '/login'), $anonymous, 1);
        $request = new Request('GET', '/admin', ['cookie' => 'rea_session=' . $authenticated->token]);

        self::assertSame(403, $this->controller->admin($request)->status());

        $this->authorization->permissions[1] = ['core.admin.access'];
        $response = $this->controller->admin($request);
        self::assertSame(200, $response->status());
        self::assertStringContainsString('Welcome, User', $response->body());
        self::assertStringContainsString('class="navigation-menu plugins-menu"', $response->body());
        self::assertStringContainsString('class="navigation-menu profile-menu"', $response->body());
        self::assertStringContainsString('<span class="profile-name">User</span>', $response->body());
        self::assertStringContainsString('<summary>Settings</summary>', $response->body());
        self::assertStringContainsString('data-theme-choice="dark"', $response->body());
        self::assertStringContainsString('href="/dashboard">Dashboard</a>', $response->body());
        self::assertStringContainsString('href="/admin">Admin</a>', $response->body());
        self::assertStringContainsString('>Logout</button>', $response->body());
    }

    public function testPluginNavigationUsesManifestMetadataAndUserAccess(): void
    {
        $this->users->users[1] = new User(1, 'user@example.com', 'hash', 'active', 'User');
        $this->plugins->records['blog'] = new PluginRecord(
            'blog',
            '1.2.3',
            'enabled',
            'hash',
            'Blog',
            'Publishing tools.',
            'Posts',
            '/cms/blog',
        );
        $this->plugins->records['gallery'] = new PluginRecord(
            'gallery',
            '1.0.0',
            'enabled',
            'hash',
            'Gallery',
            'Image galleries.',
            'Gallery',
            '/cms/gallery',
        );
        $this->plugins->records['headless'] = new PluginRecord(
            'headless',
            '1.0.0',
            'enabled',
            'hash',
            'Headless',
            'No navigation metadata.',
        );
        $this->pluginAccess->allowAll = false;
        $this->pluginAccess->assignments[1] = ['blog', 'headless'];
        $anonymous = $this->sessions->start(new Request('GET', '/login'));
        $authenticated = $this->sessions->rotate(new Request('POST', '/login'), $anonymous, 1);
        $request = new Request('GET', '/dashboard', ['cookie' => 'rea_session=' . $authenticated->token]);

        $body = $this->controller->dashboard($request)->body();
        $pluginsStart = strpos($body, 'class="navigation-menu plugins-menu"');
        $profileStart = strpos($body, 'class="navigation-menu profile-menu"');

        self::assertIsInt($pluginsStart);
        self::assertIsInt($profileStart);
        self::assertGreaterThan($pluginsStart, $profileStart);
        $pluginsMenu = substr($body, $pluginsStart, $profileStart - $pluginsStart);
        self::assertStringContainsString('href="/cms/blog"', $pluginsMenu);
        self::assertStringContainsString('Posts', $pluginsMenu);
        self::assertStringNotContainsString('/cms/gallery', $pluginsMenu);
        self::assertStringNotContainsString('/cms/media', $pluginsMenu);

        $profileMenu = substr($body, $profileStart);
        self::assertStringNotContainsString('/cms/blog', $profileMenu);
        self::assertStringContainsString('href="/dashboard"', $profileMenu);
        self::assertStringContainsString('<summary>Settings</summary>', $profileMenu);
        self::assertStringContainsString('>Logout</button>', $profileMenu);
        self::assertSame(2, substr_count($body, 'name="main-navigation" data-navigation-menu'));
        self::assertStringContainsString('/assets/navigation.js?v=1', $body);
    }

    public function testDashboardShowsOnlyActivePluginsFromTheRegistry(): void
    {
        $this->users->users[1] = new User(1, 'user@example.com', 'hash', 'active', 'User');
        $this->plugins->records['blog'] = new PluginRecord(
            'blog',
            '1.2.3',
            'enabled',
            'hash',
            'Blog',
            'Publishing tools.',
        );
        $this->plugins->records['gallery'] = new PluginRecord(
            'gallery',
            '1.0.0',
            'disabled',
            'hash',
            'Gallery',
            'Image galleries.',
        );
        $anonymous = $this->sessions->start(new Request('GET', '/login'));
        $authenticated = $this->sessions->rotate(new Request('POST', '/login'), $anonymous, 1);
        $request = new Request('GET', '/dashboard', ['cookie' => 'rea_session=' . $authenticated->token]);

        $response = $this->controller->dashboard($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Active plugins', $response->body());
        self::assertStringContainsString('Blog', $response->body());
        self::assertStringContainsString('Version 1.2.3', $response->body());
        self::assertStringContainsString('Publishing tools.', $response->body());
        self::assertStringNotContainsString('Image galleries.', $response->body());
    }

    public function testLogoutClearsAuthenticatedNavigationState(): void
    {
        $this->users->users[1] = new User(1, 'user@example.com', 'hash', 'active', 'User');
        $anonymous = $this->sessions->start(new Request('GET', '/login'));
        $authenticated = $this->sessions->rotate(new Request('POST', '/login'), $anonymous, 1);
        $request = new Request(
            'POST',
            '/logout',
            ['cookie' => 'rea_session=' . $authenticated->token],
            [],
            http_build_query(['_csrf' => $this->csrf->token($authenticated->token)]),
        );

        $response = $this->controller->logout($request);

        self::assertSame(303, $response->status());
        self::assertSame('/login', $response->header('Location'));
        self::assertStringContainsString('Max-Age=0', $response->header('Set-Cookie') ?? '');
        self::assertContains($authenticated->record->tokenHash, $this->sessionRepository->revoked);
        self::assertSame('auth.logout', $this->audit->events[0]['event']);
    }

    public function testNamespacedPluginPermissionsRemainExplicit(): void
    {
        $this->authorization->permissions[1] = ['blog.posts.update'];

        self::assertTrue($this->authorization->allows(1, 'blog.posts.update'));
        self::assertFalse($this->authorization->allows(1, 'blog.posts.delete'));
        self::assertFalse($this->authorization->allows(2, 'blog.posts.update'));
    }
}
