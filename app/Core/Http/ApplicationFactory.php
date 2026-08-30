<?php

declare(strict_types=1);

namespace ReaCms\Core\Http;

use ReaCms\Api\ApiController;
use ReaCms\Api\ApiControllerFactory;
use ReaCms\Auth\AuthController;
use ReaCms\Auth\AuthServicesFactory;
use ReaCms\Blog\BlogController;
use ReaCms\Blog\BlogControllerFactory;
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
        $auth = static fn (): AuthController => new AuthController(
            AuthServicesFactory::create($environment),
            $views,
        );
        $api = static fn (): ApiController => ApiControllerFactory::create($environment);
        $blog = static fn (): BlogController => BlogControllerFactory::create($environment);

        $router->get('/', static function (Request $request) use ($views): Response {
            $theme = ThemePreference::parse($request->cookie('rea_theme'));
            $content = $views->render('pages/home');

            return Response::html($views->render('layouts/base', [
                'title' => 'Rea CMS',
                'theme' => $theme,
                'content' => $content,
                'authenticatedUser' => null,
                'csrfToken' => null,
                'canAccessAdmin' => false,
            ]));
        });

        $router->get('/fragments/welcome', static fn (): Response => Response::html(
            $views->render('fragments/welcome'),
        ));

        $router->get('/health', static fn (): Response => Response::json(['status' => 'ok']));
        $router->get(
            '/api/v1/status.{format}',
            static fn (Request $request, array $parameters): Response => $api()->status(
                $request,
                $parameters['format'],
            ),
        );
        $router->get('/blog', static fn (Request $request): Response => $blog()->publicIndex($request));
        $router->get(
            '/blog/{slug}',
            static fn (Request $request, array $parameters): Response => $blog()->publicDetail(
                $request,
                $parameters['slug'],
            ),
        );
        $router->get(
            '/api/v1/blog.{format}',
            static fn (Request $request, array $parameters): Response => $blog()->collection(
                $request,
                $parameters['format'],
            ),
        );
        $router->get(
            '/api/v1/blog/{id}.{format}',
            static fn (Request $request, array $parameters): Response => $blog()->item(
                $request,
                (int) $parameters['id'],
                $parameters['format'],
            ),
        );
        $router->get('/login', static fn (Request $request): Response => $auth()->loginForm($request));
        $router->post('/login', static fn (Request $request): Response => $auth()->login($request));
        $router->post('/logout', static fn (Request $request): Response => $auth()->logout($request));
        $router->get('/dashboard', static fn (Request $request): Response => $auth()->dashboard($request));
        $router->get('/admin', static fn (Request $request): Response => $auth()->admin($request));
        $router->post(
            '/admin/reauthenticate',
            static fn (Request $request): Response => $auth()->reauthenticate($request),
        );
        $router->post(
            '/admin/sessions/revoke',
            static fn (Request $request): Response => $auth()->revokeSession($request),
        );
        $router->get(
            '/forgot-password',
            static fn (Request $request): Response => $auth()->forgotPasswordForm($request),
        );
        $router->post(
            '/forgot-password',
            static fn (Request $request): Response => $auth()->forgotPassword($request),
        );
        $router->get(
            '/reset-password',
            static fn (Request $request): Response => $auth()->resetPasswordForm($request),
        );
        $router->post(
            '/reset-password',
            static fn (Request $request): Response => $auth()->resetPassword($request),
        );

        return new Application($router, $errors, new SecurityHeaders());
    }
}
