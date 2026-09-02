<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\TextBlock;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Template\PluginApiRenderer;
use ReaCms\Auth\AuthServices;
use ReaCms\Auth\LoginService;
use ReaCms\Auth\PasswordHasher;
use ReaCms\Auth\PasswordResetService;
use ReaCms\Auth\SessionManager;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Routing\RouteNotFound;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Plugin\PluginRecord;
use ReaCms\Plugin\PluginRouteGate;
use ReaCms\Security\Csrf;
use ReaCms\Tests\Support\CapturingPasswordResetDelivery;
use ReaCms\Tests\Support\FrozenClock;
use ReaCms\Tests\Support\InMemoryAuditLogger;
use ReaCms\Tests\Support\InMemoryAuthorization;
use ReaCms\Tests\Support\InMemoryLoginThrottle;
use ReaCms\Tests\Support\InMemoryPasswordResetRepository;
use ReaCms\Tests\Support\InMemoryPluginAccess;
use ReaCms\Tests\Support\InMemoryPluginApiTemplateRepository;
use ReaCms\Tests\Support\InMemoryPluginRegistry;
use ReaCms\Tests\Support\InMemorySessionRepository;
use ReaCms\Tests\Support\InMemoryUserRepository;
use ReaCms\TextBlock\TextBlock;
use ReaCms\TextBlock\TextBlockController;
use ReaCms\TextBlock\TextBlockRepository;

final class TextBlockControllerTest extends TestCase
{
    public function testIdAndNameEndpointsHaveEquivalentJsonHtmlAndPlainTextRepresentations(): void
    {
        $controller = $this->controller(true);

        $json = $controller->named($this->request(), 'welcome-message', 'json');
        $html = $controller->item($this->request(), 123, 'html');
        $text = $controller->named($this->request(), 'welcome-message', 'txt');

        self::assertSame([
            'id' => 123,
            'name' => 'welcome-message',
            'content' => '<p>Welcome to our website!</p><br><p>Come in.</p>',
            'createdAt' => '2026-09-02T12:00:00+00:00',
            'updatedAt' => '2026-09-02T12:30:00+00:00',
        ], json_decode($json->body(), true, 32, JSON_THROW_ON_ERROR)['data']);
        self::assertStringContainsString('<p>Welcome to our website!</p>', $html->body());
        self::assertSame('text/plain; charset=US-ASCII', $text->header('Content-Type'));
        self::assertStringContainsString("Welcome to our website!\n\nCome in.", $text->body());
        self::assertStringNotContainsString('<', $text->body());
    }

    public function testCollectionReturnsStructuredDataAndRejectsDisabledPlugin(): void
    {
        $response = $this->controller(true)->collection($this->request(), 'json');
        $document = json_decode($response->body(), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame(1, $document['meta']['total']);
        self::assertSame('welcome-message', $document['data'][0]['name']);

        $this->expectException(RouteNotFound::class);
        $this->controller(false)->collection($this->request(), 'json');
    }

    public function testInvalidNameRouteIsNotExposed(): void
    {
        $this->expectException(RouteNotFound::class);
        $this->controller(true)->named($this->request(), '../welcome', 'json');
    }

    private function controller(bool $enabled): TextBlockController
    {
        $block = new TextBlock(
            123,
            'welcome-message',
            '<p>Welcome to our website!</p><br><p>Come in.</p>',
            new DateTimeImmutable('2026-09-02T12:00:00+00:00'),
            new DateTimeImmutable('2026-09-02T12:30:00+00:00'),
        );
        $repository = new class ($block) implements TextBlockRepository {
            public function __construct(private readonly TextBlock $block)
            {
            }
            public function all(?string $search = null): array
            {
                return [$this->block];
            }
            public function findById(int $id): ?TextBlock
            {
                return $id === $this->block->id ? $this->block : null;
            }
            public function findByName(string $name): ?TextBlock
            {
                return $name === $this->block->name ? $this->block : null;
            }
            public function create(string $name, string $content): TextBlock
            {
                return $this->block;
            }
            public function update(int $id, string $name, string $content): void
            {
            }
            public function delete(int $id): void
            {
            }
        };
        $registry = new InMemoryPluginRegistry();
        $registry->records['text_block'] = new PluginRecord(
            'text_block',
            '1.0.0',
            $enabled ? 'enabled' : 'disabled',
            str_repeat('a', 64),
        );
        $templates = new InMemoryPluginApiTemplateRepository();
        $templates->templates['text_block'] = [
            'html_list' => '{textBlock.content | sanitized_html}',
            'html_detail' => '{textBlock.content | sanitized_html}',
            'txt_list' => '{textBlock.content}',
            'txt_detail' => '{textBlock.content}',
        ];

        return new TextBlockController(
            $repository,
            new PluginRouteGate($registry),
            new OriginAllowlist(['http://rea-cms.test']),
            new PluginApiRenderer($templates),
            $this->auth($registry),
            new ViewRenderer(dirname(__DIR__, 3) . '/resources/views'),
        );
    }

    private function auth(InMemoryPluginRegistry $registry): AuthServices
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-02T13:00:00+00:00'));
        $users = new InMemoryUserRepository();
        $sessions = new InMemorySessionRepository();
        $manager = new SessionManager($sessions, $clock, 120, false);
        $passwords = new PasswordHasher();

        return new AuthServices(
            $users,
            $sessions,
            $manager,
            new LoginService($users, new InMemoryLoginThrottle(), $passwords, $clock),
            new InMemoryAuthorization(),
            new InMemoryAuditLogger(),
            new Csrf(str_repeat('k', 64)),
            new PasswordResetService(
                $users,
                new InMemoryPasswordResetRepository(),
                $sessions,
                $passwords,
                new CapturingPasswordResetDelivery(),
                $clock,
                'http://rea-cms.test',
            ),
            $registry,
            new InMemoryPluginAccess(),
        );
    }

    private function request(): Request
    {
        return new Request('GET', '/api/v1/text-block.json', ['origin' => 'http://rea-cms.test']);
    }
}
