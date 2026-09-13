<?php

declare(strict_types=1);

namespace ReaCms\Tests\Integration\Events;

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
use PDO;
use ReaCms\Api\Template\PdoPluginApiTemplateRepository;
use ReaCms\Cms\PdoCmsRepository;
use ReaCms\Database\Migrations\CoreMigrationRunner;
use ReaCms\Database\Migrations\PdoMigrationDatabase;
use ReaCms\Events\EventController;
use ReaCms\Events\EventQuery;
use ReaCms\Events\PdoEventRepository;
use ReaCms\Plugin\ManifestValidator;
use ReaCms\Plugin\PackageInspector;
use ReaCms\Plugin\PdoPluginMigrationRunner;
use ReaCms\Tests\Unit\Events\EventTest;

/** Creates a disposable database; does not read the application's .env. */
final class EventIntegrationTest extends TestCase
{
    private PDO $pdo;
    private string $database;
    private PdoEventRepository $events;
    private EventController $controller;
    private AuthServices $auth;
    private InMemoryPluginRegistry $registry;

    protected function setUp(): void
    {
        $socket = getenv('REA_EVENTS_TEST_SOCKET');
        if (!is_string($socket) || $socket === '') {
            self::markTestSkipped('Set REA_EVENTS_TEST_SOCKET to a disposable MariaDB/MySQL socket.');
        }
        $this->pdo = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->database = 'rea_events_test_' . bin2hex(random_bytes(8));
        $this->pdo->exec('CREATE DATABASE `' . $this->database . '`');
        $this->pdo->exec('USE `' . $this->database . '`');
        $root = dirname(__DIR__, 3);
        (new CoreMigrationRunner(
            new PdoMigrationDatabase($this->pdo),
            $root . '/database/migrations',
            'test_',
        ))->migrate();
        $package = (new PackageInspector(new ManifestValidator()))->inspectDirectory($root . '/plugins/events');
        (new \ReaCms\Plugin\PdoPluginRegistry($this->pdo, 'test_'))->install($package);
        $runner = new PdoPluginMigrationRunner($this->pdo, prefix: 'test_');
        $runner->apply($package);
        $runner->apply($package);
        $this->events = new PdoEventRepository($this->pdo, 'test_');
        $this->registry = new InMemoryPluginRegistry();
        $this->registry->records['events'] = new PluginRecord('events', '1.0.0', 'enabled', str_repeat('a', 64));
        $this->auth = $this->auth($this->registry);
        $this->controller = new EventController(
            $this->events,
            new PluginRouteGate($this->registry),
            new OriginAllowlist(['http://rea-cms.test']),
            new PluginApiRenderer(new PdoPluginApiTemplateRepository($this->pdo, $root . '/plugins', 'test_')),
            $this->auth,
            new ViewRenderer($root . '/resources/views'),
            new PdoCmsRepository($this->pdo, 'test_'),
            'http://rea-cms.test',
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo, $this->database)) {
            $this->pdo->exec('DROP DATABASE `' . $this->database . '`');
        }
    }

    public function testPersistenceFiltersSortAndPagination(): void
    {
        $type = $this->events->saveType(['name' => 'Comedy Show'], null);
        $id = $this->events->save([...EventTest::input(), 'type_id' => $type]);
        $this->events->save([...EventTest::input(), 'title' => 'Draft', 'published' => false]);
        $this->events->save([...EventTest::input(), 'title' => 'Another concert', 'start_date' => '2026-10-12',
            'end_date' => '2026-10-12']);
        $query = EventQuery::parse(['type' => 'comedy-show', 'search' => 'comedy', 'date' => '2026-10-10']);
        self::assertSame([$id], array_column($this->events->search($query)['data'], 'id'));
        $response = $this->controller->collection(new Request(
            'GET',
            '/api/v1/events.json',
            ['origin' => 'http://rea-cms.test'],
            ['sort' => 'name', 'perPage' => '1']
        ), 'json');
        $document = json_decode($response->body(), true);
        self::assertSame(2, $document['meta']['total']);
        self::assertSame('Another concert', $document['data'][0]['title']);
        self::assertStringContainsString('sort=name', $document['links']['next']);
        self::assertSame([], $this->events->search(EventQuery::parse(['search' => '%']))['data']);
        self::assertSame([], $this->events->search(EventQuery::parse(['start' => '2026-11-01']))['data']);
        $this->events->saveType(['name' => 'Live Comedy', 'slug' => 'live-comedy'], $type);
        self::assertSame('Live Comedy', $this->events->find($id)['type_name']);
        $this->events->deleteType($type);
        self::assertNull($this->events->find($id)['type_id']);
    }

    public function testRepresentationsTemplatesAndDynamicCalendar(): void
    {
        $id = $this->events->save(EventTest::input());
        foreach (['json', 'html', 'txt', 'ics'] as $format) {
            $response = $this->controller->item(new Request(
                'GET',
                '/api/v1/events/' . $id . '.' . $format,
                ['origin' => 'http://rea-cms.test']
            ), $id, $format);
            self::assertSame(200, $response->status());
            self::assertSame('http://rea-cms.test', $response->header('Access-Control-Allow-Origin'));
            self::assertStringContainsString('Comedy Night', $response->body());
            if ($format === 'txt') {
                self::assertStringNotContainsString('<p>', $response->body());
            }
            if ($format === 'ics') {
                self::assertSame('text/calendar; charset=UTF-8', $response->header('Content-Type'));
            }
        }
        $this->events->save([...EventTest::input(), 'title' => 'Updated event'], $id);
        $response = $this->controller->item(new Request('GET', '/', ['origin' => 'http://rea-cms.test']), $id, 'ics');
        self::assertStringContainsString('SUMMARY:Updated event', $response->body());
        $this->events->save([...EventTest::input(), 'published' => false], $id);
        $this->expectException(RouteNotFound::class);
        $this->controller->item(new Request('GET', '/', ['origin' => 'http://rea-cms.test']), $id, 'ics');
    }

    public function testAuthenticatedApiMutationsAndAdminPreview(): void
    {
        $request = $this->writeRequest(EventTest::input());
        $response = $this->controller->mutate($request, 'save');
        self::assertSame(200, $response->status(), $response->body());
        $id = json_decode($response->body(), true)['data']['id'];
        $response = $this->controller->mutate($this->writeRequest([]), 'duplicate', $id);
        $copy = json_decode($response->body(), true)['data'];
        self::assertFalse($copy['published']);
        self::assertNotSame($id, $copy['id']);
        self::assertSame(200, $this->controller->mutate($this->writeRequest([]), 'unpublish', $id)->status());
        self::assertSame(0, $this->events->search(EventQuery::parse([]))['total']);
        $sessionRequest = $this->writeRequest([]);
        $admin = new Request('GET', '/cms/events', ['cookie' => $sessionRequest->header('cookie')]);
        self::assertSame(200, $this->controller->index($admin)->status());
        self::assertStringContainsString('Comedy Night', $this->controller->form($admin, $id)->body());
        self::assertStringContainsString('Comedy Night', $this->controller->preview($admin, $id)->body());
        $this->controller->mutate($this->writeRequest([]), 'delete', $id);
        self::assertNull($this->events->find($id));
    }

    public function testAccessCsrfAndValidationFailures(): void
    {
        self::assertSame(401, $this->controller->mutate(new Request(
            'POST',
            '/api/v1/events.json',
            ['origin' => 'http://rea-cms.test'],
        ), 'save')->status());
        $good = $this->writeRequest(EventTest::input());
        $bad = new Request(
            'POST',
            '/api/v1/events.json',
            ['origin' => 'http://rea-cms.test', 'cookie' => $good->header('cookie')],
            [],
            $good->body(),
        );
        self::assertSame(419, $this->controller->mutate($bad, 'save')->status());
        self::assertSame(422, $this->controller->mutate($this->writeRequest(['title' => []]), 'save')->status());
        self::assertSame(422, $this->controller->collection(new Request(
            'GET',
            '/',
            ['origin' => 'http://rea-cms.test'],
            ['date' => 'bad'],
        ), 'json')->status());
        $this->expectException(RouteNotFound::class);
        $this->controller->collection(new Request('GET', '/', ['origin' => 'https://attacker.test']), 'json');
    }

    public function testDefaultImageFallbackAndUsageProtection(): void
    {
        $cms = new PdoCmsRepository($this->pdo, 'test_');
        $image = $cms->addMedia(['storedName' => str_repeat('a', 64), 'originalName' => 'event.jpg',
            'mime' => 'image/jpeg', 'size' => 200, 'hash' => str_repeat('a', 64)], 1);
        $other = $cms->addMedia(['storedName' => str_repeat('b', 64), 'originalName' => 'other.jpg',
            'mime' => 'image/jpeg', 'size' => 200, 'hash' => str_repeat('b', 64)], 1);
        $this->events->saveDefaultImage($image);
        $id = $this->events->save(EventTest::input());
        self::assertSame($image, $this->events->find($id)['default_image_id']);
        $this->events->save([...EventTest::input(), 'image_id' => $other], $id);
        self::assertSame($other, $this->events->find($id)['public_image_id']);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM test_media_usage')->fetchColumn());
        try {
            $this->pdo->exec('DELETE FROM test_media WHERE id = ' . $image);
            self::fail('Referenced media should be protected by the existing media usage foreign key.');
        } catch (\PDOException $exception) {
            self::assertSame('23000', $exception->getCode());
        }
        $this->events->delete($id);
        $this->events->saveDefaultImage(null);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM test_media_usage')->fetchColumn());
        $this->pdo->exec("UPDATE test_media SET visibility = 'private' WHERE id = " . $image);
        $this->expectException(\InvalidArgumentException::class);
        $this->events->saveDefaultImage($image);
    }

    public function testUpcomingPastOverlapAndDisabledPlugin(): void
    {
        $past = $this->events->save([...EventTest::input(), 'start_date' => '2000-01-01', 'end_date' => '2000-01-01']);
        $future = $this->events->save([...EventTest::input(), 'start_date' => '2099-01-01',
            'end_date' => '2099-01-03', 'all_day' => true]);
        $upcoming = $this->events->search(EventQuery::parse(['upcoming' => 'true', 'sort' => 'date']));
        self::assertSame([$future], array_column($upcoming['data'], 'id'));
        $history = $this->events->search(EventQuery::parse(['past' => 'true', 'sort' => 'date-desc']));
        self::assertSame([$past], array_column($history['data'], 'id'));
        $middle = $this->events->search(EventQuery::parse(['date' => '2099-01-02']));
        self::assertSame([$future], array_column($middle['data'], 'id'));
        $request = new Request('GET', '/', ['sec-fetch-site' => 'same-origin',
            'referer' => 'http://rea-cms.test/cms/events']);
        self::assertSame(200, $this->controller->item($request, $future, 'ics')->status());
        $this->registry->setState('events', 'disabled');
        $this->expectException(RouteNotFound::class);
        $this->controller->item($request, $future, 'ics');
    }

    /** @param array<string,mixed> $input */
    private function writeRequest(array $input): Request
    {
        $base = new Request('GET', '/cms/events');
        $session = $this->auth->sessions->rotate($base, $this->auth->sessions->start($base), 1);
        return new Request('POST', '/api/v1/events.json', [
            'origin' => 'http://rea-cms.test',
            'cookie' => SessionManager::COOKIE . '=' . $session->token,
            'x-csrf-token' => $this->auth->csrf->token($session->token), 'content-type' => 'application/json',
        ], [], json_encode((object) $input, JSON_THROW_ON_ERROR));
    }
    private function auth(InMemoryPluginRegistry $registry): AuthServices
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-02T13:00:00+00:00'));
        $users = new InMemoryUserRepository();
        $sessions = new InMemorySessionRepository();
        $manager = new SessionManager($sessions, $clock, 120, false);
        $passwords = new PasswordHasher();

        $users->users[1] = new \ReaCms\Auth\User(1, 'events@example.test', '', 'active', 'Event editor');
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
}
