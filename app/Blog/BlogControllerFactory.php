<?php

declare(strict_types=1);

namespace ReaCms\Blog;

use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Template\PdoPluginApiTemplateRepository;
use ReaCms\Api\Template\PluginApiRenderer;
use ReaCms\Core\Configuration\Environment;
use ReaCms\Database\ConnectionFactory;
use ReaCms\Plugin\PdoPluginRegistry;
use ReaCms\Plugin\PluginRouteGate;
use ReaCms\Support\SystemClock;

final class BlogControllerFactory
{
    public static function create(Environment $environment, string $projectRoot): BlogController
    {
        $pdo = ConnectionFactory::create($environment);
        $prefix = $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_';
        $configured = array_filter(array_map('trim', explode(',', $environment->get('API_ALLOWED_ORIGINS', '') ?? '')));
        $origins = new OriginAllowlist(array_values(array_unique([$environment->require('APP_URL'), ...$configured])));
        return new BlogController(
            new PdoBlogRepository($pdo),
            new PluginRouteGate(new PdoPluginRegistry($pdo, $prefix)),
            $origins,
            new SystemClock(),
            new PluginApiRenderer(new PdoPluginApiTemplateRepository($pdo, $projectRoot . '/plugins', $prefix)),
        );
    }
}
