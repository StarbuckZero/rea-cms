<?php

declare(strict_types=1);

namespace ReaCms\Setup;

use ReaCms\Core\Error\ErrorHandler;
use ReaCms\Core\Http\Application;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\SecurityHeaders;
use ReaCms\Core\Logging\LoggerFactory;
use ReaCms\Core\Routing\Router;
use ReaCms\Core\View\ViewRenderer;

final class SetupApplicationFactory
{
    public static function create(string $projectRoot): Application
    {
        $views = new ViewRenderer($projectRoot . '/resources/views');
        $environmentFile = new EnvironmentFile($projectRoot);
        $controller = new InstallController(
            $views,
            new PlatformInspector($projectRoot),
            $environmentFile,
            new SetupTokenStore($projectRoot),
            new Installer($projectRoot, $environmentFile, new InstallState($projectRoot)),
        );
        $router = new Router();
        $router->get('/install', static fn (Request $request) => $controller->form($request));
        $router->post('/install', static fn (Request $request) => $controller->install($request));
        $errors = new ErrorHandler(
            LoggerFactory::create($projectRoot . '/storage/logs/rea-cms.log', 'warning'),
            $views,
        );

        return new Application($router, $errors, new SecurityHeaders());
    }
}
