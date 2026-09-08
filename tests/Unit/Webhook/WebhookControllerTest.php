<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Webhook;

use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReaCms\Auth\AuthServices;
use ReaCms\Auth\LoginService;
use ReaCms\Auth\PasswordHasher;
use ReaCms\Auth\PasswordResetService;
use ReaCms\Auth\SessionManager;
use ReaCms\Core\Http\Request;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Jobs\PdoJobQueue;
use ReaCms\Security\Csrf;
use ReaCms\Security\SecretCipher;
use ReaCms\Tests\Support\CapturingPasswordResetDelivery;
use ReaCms\Tests\Support\FrozenClock;
use ReaCms\Tests\Support\InMemoryAuditLogger;
use ReaCms\Tests\Support\InMemoryAuthorization;
use ReaCms\Tests\Support\InMemoryLoginThrottle;
use ReaCms\Tests\Support\InMemoryPasswordResetRepository;
use ReaCms\Tests\Support\InMemoryPluginAccess;
use ReaCms\Tests\Support\InMemoryPluginRegistry;
use ReaCms\Tests\Support\InMemorySessionRepository;
use ReaCms\Tests\Support\InMemoryUserRepository;
use ReaCms\Webhook\DestinationValidator;
use ReaCms\Webhook\PdoWebhookRepository;
use ReaCms\Webhook\WebhookController;

final class WebhookControllerTest extends TestCase
{
    public function testGuestCannotReadWebhookConfiguration(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');
        $response = $this->controller($pdo, $this->auth())->handle(new Request('GET', '/admin/webhooks'));
        self::assertSame('/login', $response->header('Location'));
    }

    public function testAdministratorWithoutWebhookPermissionIsForbidden(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');
        $auth = $this->auth();
        $response = $this->controller($pdo, $auth)->handle($this->authenticated($auth, ['core.admin.access']));
        self::assertSame(403, $response->status());
    }

    public function testMissingCsrfCannotCreateDestinationOrResolveDns(): void
    {
        $pdo = $this->createMock(PDO::class);
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchAll')->willReturn([]);
        $pdo->expects(self::exactly(2))->method('prepare')->willReturnCallback(
            static function (string $sql) use ($statement): PDOStatement {
                self::assertStringStartsWith('SELECT ', $sql);
                return $statement;
            }
        );
        $auth = $this->auth();
        $request = $this->authenticated(
            $auth,
            ['core.admin.access', 'core.webhooks.manage'],
            'POST',
            'action=create&name=bad&url=https%3A%2F%2Fexample.com'
        );
        $response = $this->controller($pdo, $auth)->handle($request);
        self::assertSame(419, $response->status());
        self::assertSame('no-store, private', $response->header('Cache-Control'));
        self::assertStringContainsString('form expired', $response->body());
    }

    public function testSettingsPageEscapesDestinationNamesAndUrls(): void
    {
        $views = new ViewRenderer(dirname(__DIR__, 3) . '/resources/views');
        $html = $views->render('admin/webhooks', [
            'hooks' => [['id' => 1, 'name' => '<script>alert(1)</script>',
                'url' => 'https://example.com/?x=<script>', 'events_json' => '[]', 'status' => 'active']],
            'deliveries' => [], 'events' => ['blog.post.created'], 'csrfToken' => 'token',
            'secret' => null, 'success' => null, 'error' => null,
        ]);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    private function controller(PDO $pdo, AuthServices $auth): WebhookController
    {
        return new WebhookController(
            $auth,
            new ViewRenderer(dirname(__DIR__, 3) . '/resources/views'),
            new PdoWebhookRepository($pdo, new PdoJobQueue($pdo), new SecretCipher(str_repeat('k', 32))),
            new DestinationValidator(static function (): array {
                self::fail('Unauthorized requests must not resolve destinations.');
            }),
        );
    }

    /** @param list<string> $permissions */
    private function authenticated(
        AuthServices $auth,
        array $permissions,
        string $method = 'GET',
        string $body = '',
    ): Request {
        $id = $auth->users->create('admin@example.com', 'hash', 'Admin');
        $auth->authorization->permissions[$id] = $permissions;
        $request = new Request('GET', '/admin/webhooks');
        $session = $auth->sessions->rotate($request, $auth->sessions->start($request), $id);
        return new Request($method, '/admin/webhooks', ['cookie' => 'rea_session=' . $session->token], body: $body);
    }

    private function auth(): AuthServices
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-07T12:00:00Z'));
        $users = new InMemoryUserRepository();
        $sessions = new InMemorySessionRepository();
        $passwords = new PasswordHasher();
        return new AuthServices(
            $users,
            $sessions,
            new SessionManager($sessions, $clock),
            new LoginService($users, new InMemoryLoginThrottle(), $passwords, $clock),
            new InMemoryAuthorization(),
            new InMemoryAuditLogger(),
            new Csrf(str_repeat('k', 32)),
            new PasswordResetService(
                $users,
                new InMemoryPasswordResetRepository(),
                $sessions,
                $passwords,
                new CapturingPasswordResetDelivery(),
                $clock,
                'https://rea.example.com'
            ),
            new InMemoryPluginRegistry(),
            new InMemoryPluginAccess(),
        );
    }
}
