<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Query\ApiQuery;
use ReaCms\Api\Serialization\SerializerRegistry;
use ReaCms\Auth\AuthServices;
use ReaCms\Auth\SessionContext;
use ReaCms\Auth\User;
use ReaCms\Content\Slugger;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Routing\RouteNotFound;
use ReaCms\Core\Theme\ThemePreference;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Plugin\PluginNavigation;
use ReaCms\Plugin\PluginRouteGate;
use Throwable;

final class PodcastController
{
    public function __construct(
        private readonly PodcastRepository $repository,
        private readonly PodcastFeedSyncService $sync,
        private readonly PluginRouteGate $plugins,
        private readonly OriginAllowlist $origins,
        private readonly AuthServices $auth,
        private readonly ViewRenderer $views,
    ) {
    }

    public function collection(Request $request, string $format): Response
    {
        $this->requireApi($request);
        foreach ($this->repository->feeds(true) as $feed) {
            $this->sync->refreshIfDue($feed);
        }
        $query = ApiQuery::fromArray($request->query(), [], ['publishedAt'], 100);
        $total = $this->repository->countEpisodes(null);
        return $this->serialize($request, $format, [
            'data' => array_map(
                static fn (PodcastEpisode $episode): array => $episode->api(),
                $this->repository->episodes(null, $query->perPage, ($query->page - 1) * $query->perPage),
            ),
            'meta' => [
                'page' => $query->page,
                'perPage' => $query->perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $query->perPage),
            ],
        ]);
    }

    public function feed(Request $request, string $slug, string $format): Response
    {
        $this->requireApi($request);
        $feed = $this->findFeed($slug);
        $this->sync->refreshIfDue($feed);
        $feed = $this->repository->feedById($feed->id) ?? $feed;
        $query = ApiQuery::fromArray($request->query(), [], ['publishedAt'], 100);
        $total = $this->repository->countEpisodes($feed->id);
        return $this->serialize($request, $format, [
            'data' => [
                'podcast' => $feed->api(),
                'episodes' => array_map(
                    static fn (PodcastEpisode $episode): array => $episode->api(),
                    $this->repository->episodes(
                        $feed->id,
                        $query->perPage,
                        ($query->page - 1) * $query->perPage,
                    ),
                ),
            ],
            'meta' => [
                'page' => $query->page,
                'perPage' => $query->perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $query->perPage),
            ],
        ]);
    }

    public function episode(Request $request, string $slug, string $episode, string $format): Response
    {
        $this->requireApi($request);
        $feed = $this->findFeed($slug);
        $this->sync->refreshIfDue($feed);
        $item = $this->repository->episode($feed->id, $episode);
        if ($item === null) {
            throw new RouteNotFound();
        }
        return $this->serialize($request, $format, ['data' => $item->api()]);
    }

    public function index(Request $request): Response
    {
        return $this->page($request, 'Podcast Feeds', 'cms/podcast/index', [
            'feeds' => $this->repository->feeds(),
            'settings' => $this->repository->settings(),
            'message' => $this->queryString($request, 'message'),
        ]);
    }

    public function form(Request $request, ?int $id = null): Response
    {
        $feed = $id === null ? null : $this->repository->feedById($id);
        if ($id !== null && $feed === null) {
            return Response::redirect('/cms/podcast');
        }
        return $this->page(
            $request,
            $feed === null ? 'Add Podcast Feed' : 'Edit Podcast Feed',
            'cms/podcast/editor',
            ['feed' => $feed],
        );
    }

    public function save(Request $request, ?int $id = null): Response
    {
        $context = $this->authorized($request);
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        if (!$this->validCsrf($request, $session)) {
            return $this->failure($request, $session, $user, 419, 'Invalid request', 'errors/csrf');
        }
        $form = $request->form();
        $rssUrl = trim($form['rss_url'] ?? '');
        $interval = $this->nullableInterval($form['refresh_interval'] ?? '');
        try {
            $slug = (new Slugger())->slug($form['slug'] ?? '');
        } catch (Throwable) {
            $slug = '';
        }
        $scheme = strtolower((string) parse_url($rssUrl, PHP_URL_SCHEME));
        if (
            $slug === '' || strlen($rssUrl) > 1000 || filter_var($rssUrl, FILTER_VALIDATE_URL) === false
            || !in_array($scheme, ['http', 'https'], true)
        ) {
            return $this->failure($request, $session, $user, 422, 'Invalid podcast feed', 'cms/message', [
                'message' => 'A valid slug and HTTP or HTTPS RSS URL are required.',
            ]);
        }
        $automatic = isset($form['automatic_refresh']);
        $created = false;
        try {
            if ($id === null) {
                $feed = $this->repository->createFeed($slug, $rssUrl, $interval, $automatic);
                $created = true;
            } else {
                $previous = $this->repository->feedById($id);
                if ($previous === null) {
                    return Response::redirect('/cms/podcast');
                }
                $this->repository->updateFeed(
                    $id,
                    $slug,
                    $rssUrl,
                    isset($form['enabled']),
                    $interval,
                    $automatic,
                );
                $feed = $this->repository->feedById($id) ?? $previous;
            }
            $this->sync->forceRefresh($feed);
        } catch (Throwable $exception) {
            if ($created && isset($feed)) {
                $this->repository->deleteFeed($feed->id);
            }
            return $this->failure($request, $session, $user, 422, 'Podcast feed unavailable', 'cms/message', [
                'message' => $exception->getMessage(),
            ]);
        }
        return $this->withSession(
            Response::redirect('/cms/podcast?message=' . rawurlencode('Podcast feed saved and refreshed.')),
            $session,
        );
    }

    public function refresh(Request $request, int $id): Response
    {
        $context = $this->authorized($request);
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        if (!$this->validCsrf($request, $session)) {
            return $this->failure($request, $session, $user, 419, 'Invalid request', 'errors/csrf');
        }
        $feed = $this->repository->feedById($id);
        if ($feed === null) {
            return $this->withSession(Response::redirect('/cms/podcast'), $session);
        }
        try {
            $this->sync->forceRefresh($feed);
            $message = 'Podcast feed refreshed.';
        } catch (Throwable $exception) {
            $message = 'Refresh failed; cached data was preserved: ' . $exception->getMessage();
        }
        return $this->withSession(
            Response::redirect('/cms/podcast?message=' . rawurlencode($message)),
            $session,
        );
    }

    public function delete(Request $request, int $id): Response
    {
        $context = $this->authorized($request);
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        if (!$this->validCsrf($request, $session)) {
            return $this->failure($request, $session, $user, 419, 'Invalid request', 'errors/csrf');
        }
        $this->repository->deleteFeed($id);
        return $this->withSession(Response::redirect('/cms/podcast'), $session);
    }

    public function settings(Request $request): Response
    {
        $context = $this->authorized($request);
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        if (!$this->validCsrf($request, $session)) {
            return $this->failure($request, $session, $user, 419, 'Invalid request', 'errors/csrf');
        }
        $form = $request->form();
        $settings = new PodcastSettings(
            $this->boundedInteger($form['default_refresh_interval'] ?? '', 1, 1440, 10),
            isset($form['automatic_refresh']),
            $this->boundedInteger($form['request_timeout'] ?? '', 1, 60, 10),
            $this->boundedInteger($form['maximum_download_size'] ?? '', 65_536, 52_428_800, 5_242_880),
        );
        $this->repository->saveSettings($settings);
        return $this->withSession(
            Response::redirect('/cms/podcast?message=' . rawurlencode('Podcast settings saved.')),
            $session,
        );
    }

    private function requireApi(Request $request): void
    {
        if (!$this->plugins->exposes('podcast') || !$this->origins->allows($request->header('origin'))) {
            throw new RouteNotFound();
        }
    }

    private function findFeed(string $slug): PodcastFeed
    {
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
            throw new RouteNotFound();
        }
        $feed = $this->repository->feedBySlug($slug);
        if ($feed === null || !$feed->enabled) {
            throw new RouteNotFound();
        }
        return $feed;
    }

    /** @param array<string, mixed> $document */
    private function serialize(Request $request, string $format, array $document): Response
    {
        $serializer = (new SerializerRegistry())->get($format);
        if ($serializer === null) {
            return Response::json(['error' => ['code' => 'not_acceptable', 'message' => 'Unsupported format.']], 406);
        }
        $response = $serializer->serialize($document);
        $origin = $request->header('origin');
        return $origin === null ? $response : $response->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')->withHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    /** @param array<string, mixed> $data */
    private function page(Request $request, string $title, string $view, array $data): Response
    {
        $context = $this->authorized($request);
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $content = $this->views->render($view, [
            ...$data,
            'csrfToken' => $this->auth->csrf->token($session->token),
        ]);
        return $this->withSession($this->render($request, $user, $title, $content), $session);
    }

    /** @return array{SessionContext, User}|Response */
    private function authorized(Request $request): array|Response
    {
        $session = $this->auth->sessions->start($request);
        $user = $session->record->userId === null ? null : $this->auth->users->findById($session->record->userId);
        if ($user === null || !$user->isActive()) {
            return $this->withSession(Response::redirect('/login'), $session);
        }
        $allowed = $this->auth->plugins->find('podcast')?->state === 'enabled'
            && $this->auth->pluginAccess->allows($user->id, 'podcast');
        if (!$allowed) {
            return $this->withSession($this->render(
                $request,
                $user,
                'Access denied',
                $this->views->render('errors/forbidden'),
                403,
            ), $session);
        }
        return [$session, $user];
    }

    private function render(Request $request, User $user, string $title, string $content, int $status = 200): Response
    {
        return Response::html($this->views->render('layouts/base', [
            'title' => $title,
            'theme' => ThemePreference::parse($user->theme),
            'content' => $content,
            'authenticatedUser' => $user,
            'csrfToken' => $this->auth->csrf->token($this->auth->sessions->start($request)->token),
            'canAccessAdmin' => $this->auth->authorization->allows($user->id, 'core.admin.access'),
            'canManagePlugins' => $this->auth->authorization->allows($user->id, 'core.admin.access')
                && $this->auth->authorization->allows($user->id, 'core.plugins.view'),
            'pluginNavigation' => (new PluginNavigation(
                $this->auth->plugins,
                $this->auth->pluginAccess,
            ))->forUser($user->id),
        ]), $status);
    }

    /** @param array<string, mixed> $data */
    private function failure(
        Request $request,
        SessionContext $session,
        User $user,
        int $status,
        string $title,
        string $view,
        array $data = [],
    ): Response {
        return $this->withSession(
            $this->render($request, $user, $title, $this->views->render($view, $data), $status),
            $session,
        );
    }

    private function validCsrf(Request $request, SessionContext $session): bool
    {
        return $this->auth->csrf->validate($session->token, $request->form()['_csrf'] ?? null);
    }

    private function withSession(Response $response, SessionContext $session): Response
    {
        return $this->auth->sessions->withCookie($response, $session);
    }

    private function nullableInterval(string $value): ?int
    {
        if (trim($value) === '') {
            return null;
        }
        return $this->boundedInteger($value, 1, 1440, 10);
    }

    private function boundedInteger(string $value, int $minimum, int $maximum, int $default): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        return is_int($number) && $number >= $minimum && $number <= $maximum ? $number : $default;
    }

    private function queryString(Request $request, string $name): string
    {
        $value = $request->query()[$name] ?? '';
        return is_string($value) ? $value : '';
    }
}
