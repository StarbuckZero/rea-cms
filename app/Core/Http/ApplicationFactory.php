<?php

declare(strict_types=1);

namespace ReaCms\Core\Http;

use ReaCms\Api\ApiController;
use ReaCms\Api\ApiControllerFactory;
use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Auth\AuthController;
use ReaCms\Auth\AuthServicesFactory;
use ReaCms\Blog\BlogController;
use ReaCms\Blog\BlogControllerFactory;
use ReaCms\Cms\CmsController;
use ReaCms\Cms\PdoCmsRepository;
use ReaCms\Core\Configuration\Environment;
use ReaCms\Core\Error\ErrorHandler;
use ReaCms\Core\Logging\LoggerFactory;
use ReaCms\Core\Routing\Router;
use ReaCms\Core\Theme\ThemePreference;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Database\ConnectionFactory;

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
        $cms = static function () use ($environment, $views, $projectRoot): CmsController {
            $pdo = ConnectionFactory::create($environment);
            $prefix = $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_';
            $configured = array_filter(array_map(
                'trim',
                explode(',', $environment->get('API_ALLOWED_ORIGINS', '') ?? ''),
            ));
            return new CmsController(
                AuthServicesFactory::create($environment),
                new PdoCmsRepository($pdo, $prefix),
                $views,
                $projectRoot . '/storage/uploads',
                new OriginAllowlist(array_values(array_unique([
                    $environment->require('APP_URL'),
                    ...$configured,
                ]))),
            );
        };

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
                'pluginNavigation' => [],
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
        $router->get('/cms/blog', static fn (Request $request): Response => $cms()->blogIndex($request));
        $router->get('/cms/blog/new', static fn (Request $request): Response => $cms()->blogForm($request));
        $router->post('/cms/blog', static fn (Request $request): Response => $cms()->saveBlog($request));
        $router->get(
            '/cms/blog/{id}/edit',
            static fn (Request $request, array $parameters): Response => $cms()->blogForm(
                $request,
                (int) $parameters['id'],
            ),
        );
        $router->post(
            '/cms/blog/{id}',
            static fn (Request $request, array $parameters): Response => $cms()->saveBlog(
                $request,
                (int) $parameters['id'],
            ),
        );
        $router->post(
            '/cms/blog/{id}/delete',
            static fn (Request $request, array $parameters): Response => $cms()->deleteBlog(
                $request,
                (int) $parameters['id'],
            ),
        );
        $router->get('/cms/gallery', static fn (Request $request): Response => $cms()->galleryIndex($request));
        $router->get(
            '/cms/gallery/albums',
            static fn (Request $request): Response => $cms()->galleryAlbumIndex($request),
        );
        $router->get(
            '/cms/gallery/albums/new',
            static fn (Request $request): Response => $cms()->galleryAlbumForm($request),
        );
        $router->post(
            '/cms/gallery/albums',
            static fn (Request $request): Response => $cms()->saveGalleryAlbum($request),
        );
        $router->get(
            '/cms/gallery/albums/{id}/edit',
            static fn (Request $request, array $parameters): Response => $cms()->galleryAlbumForm(
                $request,
                (int) $parameters['id'],
            ),
        );
        $router->post(
            '/cms/gallery/albums/{id}',
            static fn (Request $request, array $parameters): Response => $cms()->saveGalleryAlbum(
                $request,
                (int) $parameters['id'],
            ),
        );
        $router->post(
            '/cms/gallery/albums/{id}/delete',
            static fn (Request $request, array $parameters): Response => $cms()->deleteGalleryAlbum(
                $request,
                (int) $parameters['id'],
            ),
        );
        $router->post(
            '/cms/gallery/albums/{id}/reorder',
            static fn (Request $request, array $parameters): Response => $cms()->reorderGalleryAlbum(
                $request,
                (int) $parameters['id'],
            ),
        );
        $router->get('/cms/gallery/new', static fn (Request $request): Response => $cms()->galleryForm($request));
        $router->post('/cms/gallery', static fn (Request $request): Response => $cms()->saveGallery($request));
        $router->get(
            '/cms/gallery/{id}/edit',
            static fn (Request $request, array $parameters): Response => $cms()->galleryForm(
                $request,
                (int) $parameters['id'],
            ),
        );
        $router->post(
            '/cms/gallery/{id}',
            static fn (Request $request, array $parameters): Response => $cms()->saveGallery(
                $request,
                (int) $parameters['id'],
            ),
        );
        $router->post(
            '/cms/gallery/{id}/delete',
            static fn (Request $request, array $parameters): Response => $cms()->deleteGallery(
                $request,
                (int) $parameters['id'],
            ),
        );
        $router->get('/cms/media', static fn (Request $request): Response => $cms()->mediaIndex($request));
        $router->post('/cms/media', static fn (Request $request): Response => $cms()->uploadMedia($request));
        $router->get(
            '/cms/media/{id}',
            static fn (Request $request, array $parameters): Response => $cms()->medium(
                $request,
                (int) $parameters['id'],
            ),
        );
        $router->get(
            '/media/{id}',
            static fn (Request $request, array $parameters): Response => $cms()->publicMedium(
                (int) $parameters['id'],
            ),
        );
        $router->get(
            '/api/v1/gallery.{format}',
            static fn (Request $request, array $parameters): Response => $cms()->galleryFeed(
                $request,
                $parameters['format'],
            ),
        );
        $router->get(
            '/api/v1/gallery/albums.{format}',
            static fn (Request $request, array $parameters): Response => $cms()->galleryAlbumsFeed(
                $request,
                $parameters['format'],
            ),
        );
        $router->get(
            '/api/v1/gallery/albums/{id}/items.{format}',
            static fn (Request $request, array $parameters): Response => $cms()->galleryAlbumItemsFeed(
                $request,
                (int) $parameters['id'],
                $parameters['format'],
            ),
        );
        $router->get(
            '/api/v1/gallery/albums/{id}.{format}',
            static fn (Request $request, array $parameters): Response => $cms()->galleryAlbumFeed(
                $request,
                (int) $parameters['id'],
                $parameters['format'],
            ),
        );
        $router->get(
            '/api/v1/gallery/{id}.{format}',
            static fn (Request $request, array $parameters): Response => $cms()->galleryApiItem(
                $request,
                (int) $parameters['id'],
                $parameters['format'],
            ),
        );
        $router->get('/admin', static fn (Request $request): Response => $auth()->admin($request));
        $router->post('/admin/users', static fn (Request $request): Response => $auth()->createUser($request));
        $router->post(
            '/admin/users/{id}',
            static fn (Request $request, array $parameters): Response => $auth()->updateUser(
                $request,
                (int) $parameters['id'],
            ),
        );
        $router->post(
            '/admin/users/{id}/delete',
            static fn (Request $request, array $parameters): Response => $auth()->deleteUser(
                $request,
                (int) $parameters['id'],
            ),
        );
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
