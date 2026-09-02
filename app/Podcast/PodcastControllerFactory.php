<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Template\PdoPluginApiTemplateRepository;
use ReaCms\Api\Template\PluginApiRenderer;
use ReaCms\Auth\AuthServicesFactory;
use ReaCms\Core\Configuration\Environment;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Database\ConnectionFactory;
use ReaCms\Plugin\PdoPluginRegistry;
use ReaCms\Plugin\PluginRouteGate;
use ReaCms\Support\SystemClock;

final class PodcastControllerFactory
{
    public static function create(Environment $environment, string $projectRoot): PodcastController
    {
        $pdo = ConnectionFactory::create($environment);
        $prefix = $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_';
        $repository = new PdoPodcastRepository($pdo);
        $schedule = new PodcastSchedule();
        $configured = array_filter(array_map(
            'trim',
            explode(',', $environment->get('API_ALLOWED_ORIGINS', '') ?? ''),
        ));
        return new PodcastController(
            $repository,
            new PodcastFeedSyncService(
                $repository,
                new CurlFeedFetcher(),
                new PodcastFeedParser(),
                new SystemClock(),
            ),
            new PluginRouteGate(new PdoPluginRegistry($pdo, $prefix)),
            new OriginAllowlist(array_values(array_unique([
                $environment->require('APP_URL'),
                ...$configured,
            ]))),
            AuthServicesFactory::create($environment),
            new ViewRenderer($projectRoot . '/resources/views'),
            new PluginApiRenderer(new PdoPluginApiTemplateRepository($pdo, $projectRoot . '/plugins', $prefix)),
            $schedule,
            $schedule->defaultTimezone($environment->get('APP_TIMEZONE')),
        );
    }
}
