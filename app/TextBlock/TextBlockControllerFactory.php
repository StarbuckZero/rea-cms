<?php

declare(strict_types=1);

namespace ReaCms\TextBlock;

use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Template\PdoPluginApiTemplateRepository;
use ReaCms\Api\Template\PluginApiRenderer;
use ReaCms\Auth\AuthServicesFactory;
use ReaCms\Core\Configuration\Environment;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Database\ConnectionFactory;
use ReaCms\Plugin\PdoPluginRegistry;
use ReaCms\Plugin\PluginRouteGate;

final class TextBlockControllerFactory
{
    public static function create(Environment $environment, string $projectRoot): TextBlockController
    {
        $pdo = ConnectionFactory::create($environment);
        $prefix = $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_';
        $configured = array_filter(array_map(
            'trim',
            explode(',', $environment->get('API_ALLOWED_ORIGINS', '') ?? ''),
        ));

        return new TextBlockController(
            new PdoTextBlockRepository($pdo),
            new PluginRouteGate(new PdoPluginRegistry($pdo, $prefix)),
            new OriginAllowlist(array_values(array_unique([
                $environment->require('APP_URL'),
                ...$configured,
            ]))),
            new PluginApiRenderer(new PdoPluginApiTemplateRepository(
                $pdo,
                $projectRoot . '/plugins',
                $prefix,
            )),
            AuthServicesFactory::create($environment),
            new ViewRenderer($projectRoot . '/resources/views'),
        );
    }
}
