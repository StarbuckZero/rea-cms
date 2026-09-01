<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Plugin;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Api\RateLimit\RateLimitDecision;
use ReaCms\Api\RateLimit\RateLimiter;
use ReaCms\Auth\AuthServices;
use ReaCms\Auth\LoginService;
use ReaCms\Auth\PasswordHasher;
use ReaCms\Auth\PasswordResetService;
use ReaCms\Auth\SessionContext;
use ReaCms\Auth\SessionManager;
use ReaCms\Auth\User;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\UploadedFile;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Plugin\ManifestValidator;
use ReaCms\Plugin\PackageInspector;
use ReaCms\Plugin\PendingPackageStore;
use ReaCms\Plugin\PluginLifecycle;
use ReaCms\Plugin\PluginManagementController;
use ReaCms\Plugin\PluginRecord;
use ReaCms\Security\Csrf;
use ReaCms\Tests\Support\CapturingPasswordResetDelivery;
use ReaCms\Tests\Support\FrozenClock;
use ReaCms\Tests\Support\InMemoryAuditLogger;
use ReaCms\Tests\Support\InMemoryAuthorization;
use ReaCms\Tests\Support\InMemoryLoginThrottle;
use ReaCms\Tests\Support\InMemoryPasswordResetRepository;
use ReaCms\Tests\Support\InMemoryPluginAccess;
use ReaCms\Tests\Support\InMemoryPluginDataManager;
use ReaCms\Tests\Support\InMemoryPluginRegistry;
use ReaCms\Tests\Support\InMemorySessionRepository;
use ReaCms\Tests\Support\InMemoryUserRepository;
use ZipArchive;

final class PluginManagementControllerTest extends TestCase
{
    private string $root;
    private FrozenClock $clock;
    private InMemoryAuthorization $authorization;
    private InMemoryPluginRegistry $plugins;
    private InMemoryPluginDataManager $data;
    private SessionManager $sessions;
    private Csrf $csrf;
    private PluginManagementController $controller;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rea-plugin-admin-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root . '/staging', 0700, true));
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-08-31T12:00:00+00:00'));
        $users = new InMemoryUserRepository();
        $users->users[1] = new User(1, 'admin@example.com', 'hash', 'active', 'Admin');
        $sessionRepository = new InMemorySessionRepository();
        $this->sessions = new SessionManager($sessionRepository, $this->clock, 120, false);
        $this->authorization = new InMemoryAuthorization();
        $this->plugins = new InMemoryPluginRegistry();
        $this->data = new InMemoryPluginDataManager($this->root);
        $this->csrf = new Csrf(str_repeat('k', 64));
        $passwords = new PasswordHasher();
        $services = new AuthServices(
            $users,
            $sessionRepository,
            $this->sessions,
            new LoginService($users, new InMemoryLoginThrottle(), $passwords, $this->clock),
            $this->authorization,
            new InMemoryAuditLogger(),
            $this->csrf,
            new PasswordResetService(
                $users,
                new InMemoryPasswordResetRepository(),
                $sessionRepository,
                $passwords,
                new CapturingPasswordResetDelivery(),
                $this->clock,
                'https://cms.example.com',
            ),
            $this->plugins,
            new InMemoryPluginAccess(),
        );
        $validator = new ManifestValidator();
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
        $this->controller = new PluginManagementController(
            $services,
            new ViewRenderer(dirname(__DIR__, 3) . '/resources/views'),
            new PackageInspector($validator),
            new PendingPackageStore($this->root . '/staging', $validator),
            new PluginLifecycle(
                $this->plugins,
                $services->audit,
                $this->root . '/plugins',
                $this->root . '/file-backups',
                $this->root . '/cache',
            ),
            $this->data,
            $rateLimiter,
            $this->clock,
            $this->root . '/staging',
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testIndexRequiresAdministratorAndPluginViewPermissions(): void
    {
        $session = $this->authenticatedSession();
        $request = $this->request('GET', '/admin/plugins', $session);
        self::assertSame(403, $this->controller->index($request)->status());

        $this->authorization->permissions[1] = ['core.admin.access', 'core.plugins.view'];
        $this->plugins->records['notes'] = $this->record('disabled');
        $response = $this->controller->index($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Plugin Management', $response->body());
        self::assertStringContainsString('Example Author', $response->body());
        self::assertStringContainsString('Plugin ID: <code>notes</code>', $response->body());
        self::assertStringNotContainsString('Validate plugin', $response->body());
    }

    public function testDisableAndDataPreservingRemovalUseCsrfTypedConfirmationAndLifecycleState(): void
    {
        $this->authorization->permissions[1] = [
            'core.admin.access',
            'core.plugins.view',
            'core.plugins.manage',
            'core.plugins.purge',
        ];
        $this->plugins->records['notes'] = $this->record('enabled');
        self::assertTrue(mkdir($this->root . '/plugins/notes', 0700, true));
        $session = $this->recentlyReauthenticatedSession();

        $disable = $this->controller->disable($this->formRequest(
            '/admin/plugins/notes/disable',
            $session,
            [],
        ), 'notes');
        self::assertSame(303, $disable->status());
        self::assertSame('disabled', $this->plugins->records['notes']->state);

        $invalid = $this->controller->uninstall($this->formRequest(
            '/admin/plugins/notes/uninstall',
            $session,
            ['mode' => 'keep_data', 'confirmation' => 'notes'],
        ), 'notes');
        self::assertSame(422, $invalid->status());
        self::assertSame('disabled', $this->plugins->records['notes']->state);

        $removed = $this->controller->uninstall($this->formRequest(
            '/admin/plugins/notes/uninstall',
            $session,
            ['mode' => 'keep_data', 'confirmation' => 'REMOVE notes'],
        ), 'notes');
        self::assertSame(303, $removed->status());
        self::assertSame('uninstalled', $this->plugins->records['notes']->state);
        self::assertSame(0, $this->data->exports);
        self::assertSame(0, $this->data->purges);
    }

    public function testValidatedUploadRequiresReviewThenInstallsDisabled(): void
    {
        $this->authorization->permissions[1] = [
            'core.admin.access',
            'core.plugins.view',
            'core.plugins.manage',
        ];
        $session = $this->recentlyReauthenticatedSession();
        $archive = $this->root . '/notes.zip';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        self::assertTrue($zip->addFromString('notes/plugin.json', json_encode([
            'schemaVersion' => 1,
            'id' => 'notes',
            'name' => 'Notes',
            'version' => '1.0.0',
            'reaCmsVersion' => '^1.0',
            'description' => 'Notes.',
            'author' => 'Example Author',
            'tables' => ['plugin_notes_entries'],
            'permissions' => [],
        ], JSON_THROW_ON_ERROR)));
        self::assertTrue($zip->close());
        $body = http_build_query(['_csrf' => $this->csrf->token($session->token)]);
        $inspect = $this->controller->inspect(new Request(
            'POST',
            '/admin/plugins/inspect',
            ['cookie' => 'rea_session=' . $session->token],
            [],
            $body,
            files: ['plugin_zip' => new UploadedFile('notes.zip', $archive, UPLOAD_ERR_OK, filesize($archive))],
        ));

        self::assertSame(200, $inspect->status());
        self::assertStringContainsString('Confirm Notes', $inspect->body());
        self::assertStringContainsString('Example Author', $inspect->body());
        self::assertMatchesRegularExpression('/name="pending_token" value="[a-f0-9]{48}"/', $inspect->body());
        preg_match('/name="pending_token" value="([a-f0-9]{48})"/', $inspect->body(), $matches);

        $install = $this->controller->install($this->formRequest(
            '/admin/plugins/install',
            $session,
            ['pending_token' => $matches[1], 'confirm_install' => '1'],
        ));
        self::assertSame(303, $install->status());
        self::assertSame('disabled', $this->plugins->find('notes')?->state);
        self::assertFileExists($this->root . '/plugins/notes/plugin.json');
    }

    public function testPermanentRemovalAlwaysCreatesFinalExportBeforePurgingData(): void
    {
        $this->authorization->permissions[1] = [
            'core.admin.access',
            'core.plugins.view',
            'core.plugins.manage',
            'core.plugins.purge',
        ];
        $this->plugins->records['notes'] = $this->record('uninstalled');
        $session = $this->recentlyReauthenticatedSession();

        $response = $this->controller->uninstall($this->formRequest(
            '/admin/plugins/notes/uninstall',
            $session,
            ['mode' => 'delete_data', 'confirmation' => 'PURGE notes'],
        ), 'notes');

        self::assertSame(303, $response->status());
        self::assertSame(1, $this->data->exports);
        self::assertSame(1, $this->data->purges);
        self::assertNull($this->plugins->find('notes'));
    }

    private function record(string $state): PluginRecord
    {
        return new PluginRecord(
            'notes',
            '1.2.3',
            $state,
            hash('sha256', 'notes'),
            'Notes',
            'Structured notes.',
            'Notes',
            '/cms/notes',
            'Example Author',
            ['plugin_notes_entries'],
        );
    }

    private function authenticatedSession(): SessionContext
    {
        $anonymous = $this->sessions->start(new Request('GET', '/login'));
        return $this->sessions->rotate(new Request('POST', '/login'), $anonymous, 1);
    }

    private function recentlyReauthenticatedSession(): SessionContext
    {
        $session = $this->authenticatedSession();
        $this->sessions->markReauthenticated($session);
        return $this->sessions->start($this->request('GET', '/admin', $session));
    }

    /** @param array<string, string> $values */
    private function formRequest(string $path, SessionContext $session, array $values): Request
    {
        return $this->request('POST', $path, $session, http_build_query([
            '_csrf' => $this->csrf->token($session->token),
            ...$values,
        ]));
    }

    private function request(
        string $method,
        string $path,
        SessionContext $session,
        string $body = '',
    ): Request {
        return new Request($method, $path, ['cookie' => 'rea_session=' . $session->token], [], $body);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
