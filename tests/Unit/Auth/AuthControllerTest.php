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
        self::assertSame('/admin', $response->header('Location'));
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
        self::assertStringContainsString('class="profile-menu"', $response->body());
        self::assertStringContainsString('<span>User</span>', $response->body());
        self::assertStringContainsString('<summary>Settings</summary>', $response->body());
        self::assertStringContainsString('data-theme-choice="dark"', $response->body());
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
