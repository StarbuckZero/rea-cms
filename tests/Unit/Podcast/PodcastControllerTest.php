<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Podcast;

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
use ReaCms\Podcast\FeedFetcher;
use ReaCms\Podcast\FeedFetchResult;
use ReaCms\Podcast\PodcastController;
use ReaCms\Podcast\PodcastFeed;
use ReaCms\Podcast\PodcastEpisode;
use ReaCms\Podcast\PodcastFeedParser;
use ReaCms\Podcast\PodcastFeedSyncService;
use ReaCms\Podcast\PodcastSettings;
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
use ReaCms\Tests\Support\InMemoryPodcastRepository;
use ReaCms\Tests\Support\InMemorySessionRepository;
use ReaCms\Tests\Support\InMemoryUserRepository;

final class PodcastControllerTest extends TestCase
{
    public function testPodcastDirectoryReturnsOnlyEnabledPodcastSummaries(): void
    {
        $controller = $this->controller(true);
        $response = $controller->podcasts($this->request());
        $document = json_decode($response->body(), true, 32, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->status());
        self::assertSame(1, $document['meta']['total']);
        self::assertSame([[
            'id' => 1,
            'slug' => 'first-show',
            'title' => 'First Show',
            'description' => 'The first podcast.',
            'imageUrl' => 'https://example.com/first.jpg',
        ]], $document['data']);
        self::assertSame('http://rea-cms.test', $response->header('Access-Control-Allow-Origin'));
    }

    public function testDisabledPodcastPluginDoesNotExposeDirectory(): void
    {
        $this->expectException(RouteNotFound::class);
        $this->controller(false)->podcasts($this->request());
    }

    public function testFormattedFieldsReachJsonAndTemplates(): void
    {
        $controller = $this->controller(true);
        foreach (['json', 'html', 'txt'] as $format) {
            $responses = [
                $controller->collection($this->request(), $format),
                $controller->episode($this->request(), 'first-show', 'episode-one', $format),
            ];
            foreach ($responses as $response) {
                self::assertSame(200, $response->status());
                self::assertStringContainsString('1 hour 25 minutes', $response->body());
                self::assertStringContainsString('September 8, 2026', $response->body());
                self::assertStringContainsString('8:30 PM', $response->body());
            }
        }
        $response = $controller->feed($this->request(), 'first-show', 'json');
        $document = json_decode($response->body(), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame('8:30 PM', $document['data']['episodes'][0]['publishedTime']);
        self::assertSame(5100, $document['data']['episodes'][0]['audio']['durationSeconds']);
    }

    private function controller(bool $enabled): PodcastController
    {
        $repository = new InMemoryPodcastRepository();
        $repository->records = [
            1 => new PodcastFeed(
                1,
                'first-show',
                'https://example.com/first.xml',
                true,
                null,
                false,
                title: 'First Show',
                description: 'The first podcast.',
                imageUrl: 'https://example.com/first.jpg',
                scheduleTimezone: 'America/New_York',
            ),
            2 => new PodcastFeed(
                2,
                'hidden-show',
                'https://example.com/hidden.xml',
                false,
                null,
                false,
                title: 'Hidden Show',
                description: 'Not publicly listed.',
                imageUrl: 'https://example.com/hidden.jpg',
            ),
        ];
        $repository->episodeRecords = [new PodcastEpisode(
            1,
            1,
            'first-show',
            'First Show',
            'episode-1',
            'episode-one',
            'Episode One',
            '',
            '',
            '',
            '',
            null,
            '',
            5100,
            '',
            false,
            'full',
            new DateTimeImmutable('2026-09-09T00:30:00Z'),
        )];
        $templates = new InMemoryPluginApiTemplateRepository();
        $template = '{podcast.audio.durationFormatted} | {podcast.publishedDate} | {podcast.publishedTime}';
        $templates->save('podcast', [
            'html_list' => $template, 'html_detail' => $template,
            'txt_list' => $template, 'txt_detail' => $template,
        ]);
        $fetcher = new class implements FeedFetcher {
            public function fetch(PodcastFeed $feed, PodcastSettings $settings): FeedFetchResult
            {
                return new FeedFetchResult(304);
            }
        };
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-06T12:00:00+00:00'));
        $registry = new InMemoryPluginRegistry();
        $registry->records['podcast'] = new PluginRecord(
            'podcast',
            '1.0.0',
            $enabled ? 'enabled' : 'disabled',
            str_repeat('a', 64),
        );

        return new PodcastController(
            $repository,
            new PodcastFeedSyncService($repository, $fetcher, new PodcastFeedParser(), $clock),
            new PluginRouteGate($registry),
            new OriginAllowlist(['http://rea-cms.test']),
            $this->auth($registry, $clock),
            new ViewRenderer(dirname(__DIR__, 3) . '/resources/views'),
            new PluginApiRenderer($templates),
        );
    }

    private function auth(InMemoryPluginRegistry $registry, FrozenClock $clock): AuthServices
    {
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
        return new Request('GET', '/api/v1/podcasts.json', ['origin' => 'http://rea-cms.test']);
    }
}
