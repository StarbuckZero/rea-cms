<?php

declare(strict_types=1);

namespace ReaCms\Core\Http;

use ReaCms\Core\Configuration\Environment;
use ReaCms\Core\Error\ErrorHandler;
use ReaCms\Core\Logging\LoggerFactory;
use ReaCms\Core\Routing\Router;
use ReaCms\Core\Theme\ThemePreference;
use ReaCms\Core\View\ViewRenderer;

final class ApplicationFactory
{
    public static function create(Environment $environment, string $projectRoot): Application
    {
        $views = new ViewRenderer($projectRoot . '/resources/views');
        $logger = LoggerFactory::create(
            $projectRoot . '/storage/logs/rea-cms.log',
            $environment->get('LOG_LEVEL', 'warning') ?? 'warning',
        );
        $errors = new ErrorHandler($logger, $views, $environment->bool('APP_DEBUG'));
        $router = new Router();

        $router->get('/', static function (Request $request) use ($views): Response {
            $theme = ThemePreference::parse($request->cookie('rea_theme'));
            $content = $views->render('pages/home');

            return Response::html($views->render('layouts/base', [
                'title' => 'Rea CMS',
                'theme' => $theme,
                'content' => $content,
            ]));
        });

        $router->get('/fragments/welcome', static fn (): Response => Response::html(
            $views->render('fragments/welcome'),
        ));

        $router->get('/health', static fn (): Response => Response::json(['status' => 'ok']));

        return new Application($router, $errors, new SecurityHeaders());
    }
}
