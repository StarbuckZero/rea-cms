<?php

declare(strict_types=1);

namespace ReaCms\Events;

use InvalidArgumentException;
use JsonException;
use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Template\PluginApiRenderer;
use ReaCms\Auth\AuthServices;
use ReaCms\Auth\SessionContext;
use ReaCms\Auth\User;
use ReaCms\Cms\PdoCmsRepository;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Routing\RouteNotFound;
use ReaCms\Core\Theme\ThemePreference;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Plugin\PluginNavigation;
use ReaCms\Plugin\PluginRouteGate;

final class EventController
{
    public function __construct(
        private readonly PdoEventRepository $repository,
        private readonly PluginRouteGate $plugins,
        private readonly OriginAllowlist $origins,
        private readonly PluginApiRenderer $api,
        private readonly AuthServices $auth,
        private readonly ViewRenderer $views,
        private readonly PdoCmsRepository $media,
        private readonly string $siteUrl,
    ) {
    }

    public function collection(Request $request, string $format): Response
    {
        $this->requireApi($request);
        try {
            $query = EventQuery::parse($request->query());
        } catch (InvalidArgumentException $exception) {
            return $this->cors($request, $this->invalid($exception));
        }
        $result = $this->repository->search($query);
        $pages = (int) ceil($result['total'] / $query->perPage);
        $link = static fn (int $page): string => '/api/v1/events.' . rawurlencode($format) . '?'
            . http_build_query([...$request->query(), 'page' => $page, 'perPage' => $query->perPage]);
        return $this->serialize($request, $format, 'list', [
            'data' => array_map(
                fn (array $row): array => (new EventPresenter())->present($row, $format !== 'json'),
                $result['data'],
            ),
            'meta' => ['page' => $query->page, 'perPage' => $query->perPage,
                'total' => $result['total'], 'totalPages' => $pages],
            'links' => ['self' => $link($query->page),
                'next' => $query->page < $pages ? $link($query->page + 1) : null,
                'previous' => $query->page > 1 ? $link($query->page - 1) : null],
        ]);
    }

    public function item(Request $request, int $id, string $format): Response
    {
        $this->requireApi($request);
        $event = $this->repository->find($id, true);
        if ($event === null) {
            throw new RouteNotFound();
        }
        if ($format === 'ics') {
            return $this->cors($request, new Response(
                (new EventCalendar())->render((new EventPresenter())->present($event), $this->siteUrl),
                200,
                ['Content-Type' => 'text/calendar; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="event-' . $id . '.ics"',
                    'Cache-Control' => 'no-cache'],
            ));
        }
        return $this->serialize($request, $format, 'detail', [
            'data' => (new EventPresenter())->present($event, $format !== 'json'),
        ]);
    }

    public function apiTypes(Request $request): Response
    {
        $this->requireApi($request);
        return $this->cors($request, Response::json(['data' => array_map(static function (array $type): array {
            $type['id'] = (int) $type['id'];
            return $type;
        }, $this->repository->types())]));
    }

    public function index(Request $request): Response
    {
        $context = $this->authorized($request);
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        try {
            $query = EventQuery::parse($request->query());
        } catch (InvalidArgumentException $exception) {
            return $this->failure(
                $request,
                $session,
                $user,
                422,
                'Invalid filters',
                'cms/message',
                ['message' => $exception->getMessage()]
            );
        }
        $result = $this->repository->search($query, false);
        return $this->page($request, $session, $user, 'Events', 'cms/events/index', [
            'events' => $result['data'], 'total' => $result['total'], 'query' => $query,
            'filters' => $request->query(), 'types' => $this->repository->types(),
            'defaultImage' => $this->repository->defaultImage(), 'media' => $this->media->images(true),
        ]);
    }

    public function form(Request $request, ?int $id = null): Response
    {
        $context = $this->authorized($request);
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $event = $id === null ? null : $this->repository->find($id);
        if ($id !== null && $event === null) {
            throw new RouteNotFound();
        }
        return $this->page($request, $session, $user, 'Edit event', 'cms/events/editor', [
            'event' => $event, 'types' => $this->repository->types(), 'media' => $this->media->images(true),
        ]);
    }

    public function preview(Request $request, int $id): Response
    {
        $context = $this->authorized($request);
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $event = $this->repository->find($id);
        if ($event === null) {
            throw new RouteNotFound();
        }
        $preview = $this->api->render('events', 'event', 'html', 'detail', [
            'data' => (new EventPresenter())->present($event, true),
        ]);
        return $this->withSession($this->render($request, $user, 'Event preview', $preview?->body() ?? ''), $session)
            ->withHeader('Cache-Control', 'private, no-store');
    }

    public function mutate(Request $request, string $action, ?int $id = null): Response
    {
        if (str_starts_with($request->path(), '/api/')) {
            $this->requireApi($request);
        }
        $context = $this->authorized($request);
        if ($context instanceof Response) {
            return $this->cors($request, $context);
        }
        [$session, $user] = $context;
        if (
            !$this->auth->csrf->validate(
                $session->token,
                $request->header('x-csrf-token') ?? $request->form()['_csrf'] ?? null
            )
        ) {
            return $this->cors($request, $this->withSession(Response::json([
                'error' => ['code' => 'invalid_csrf', 'message' => 'Invalid request token.'],
            ], 419), $session));
        }
        try {
            $input = $this->input($request);
            $event = $id === null ? null : $this->repository->find($id);
            if (
                in_array($action, ['save', 'delete', 'duplicate', 'publish', 'unpublish'], true)
                && $id !== null && $event === null
            ) {
                throw new RouteNotFound();
            }
            switch ($action) {
                case 'save':
                    $id = $this->repository->save([...($event ?? []), ...$input], $id);
                    break;
                case 'delete':
                    $this->repository->delete((int) $id);
                    break;
                case 'duplicate':
                    $id = $this->repository->save([...($event ?? []),
                        'title' => mb_strcut((string) ($event['title'] ?? ''), 0, 245, 'UTF-8') . ' (copy)',
                        'published' => false]);
                    break;
                case 'publish':
                case 'unpublish':
                    $this->repository->save([...($event ?? []), 'published' => $action === 'publish'], $id);
                    break;
                case 'type-save':
                    if ($id !== null && $this->repository->type($id) === null) {
                        throw new RouteNotFound();
                    }
                    $id = $this->repository->saveType($input, $id);
                    break;
                case 'type-delete':
                    $this->repository->deleteType((int) $id);
                    break;
                case 'settings':
                    $value = $input['default_image_id'] ?? '';
                    $image = $value === '' ? null : filter_var($value, FILTER_VALIDATE_INT);
                    if ($image !== null && (!is_int($image) || $image < 1)) {
                        throw new InvalidArgumentException('Choose a valid default image.');
                    }
                    $this->repository->saveDefaultImage($image);
                    break;
                default:
                    throw new RouteNotFound();
            }
        } catch (InvalidArgumentException | JsonException $exception) {
            if ($request->expectsJson()) {
                return $this->cors($request, $this->withSession($this->invalid($exception), $session));
            }
            // Preserve submitted values so a validation error does not discard an event draft.
            if ($action === 'save') {
                return $this->page($request, $session, $user, 'Check event', 'cms/events/editor', [
                    'event' => [...($event ?? []), ...($input ?? []), 'id' => $id],
                    'types' => $this->repository->types(), 'media' => $this->media->images(true),
                    'error' => $exception->getMessage(),
                ], 422);
            }
            return $this->failure(
                $request,
                $session,
                $user,
                422,
                'Event not saved',
                'cms/message',
                ['message' => $exception->getMessage()]
            );
        }
        if ($request->expectsJson()) {
            $data = match ($action) {
                'type-save' => $this->repository->type((int) $id),
                'delete', 'type-delete', 'settings' => ['success' => true],
                default => (new EventPresenter())->present($this->repository->find((int) $id) ?? []),
            };
            return $this->cors($request, $this->withSession(Response::json(['data' => $data]), $session));
        }
        $path = in_array($action, ['save', 'duplicate'], true) ? '/cms/events/' . $id . '/edit' : '/cms/events';
        return $this->withSession(Response::redirect($path), $session);
    }

    /** @return array<string,mixed> */
    private function input(Request $request): array
    {
        if (!str_contains(strtolower($request->header('content-type') ?? ''), 'application/json')) {
            return $request->form();
        }
        $input = json_decode($request->body(), false, 32, JSON_THROW_ON_ERROR);
        if (!$input instanceof \stdClass) {
            throw new InvalidArgumentException('Provide a JSON object.');
        }
        return get_object_vars($input);
    }

    private function invalid(\Throwable $exception): Response
    {
        return Response::json(['error' => ['code' => 'validation_failed', 'message' => $exception->getMessage()]], 422);
    }

    private function requireApi(Request $request): void
    {
        $origin = $request->header('origin');
        // Browsers omit Origin on same-origin GET navigation, including calendar downloads.
        if ($origin === null && $request->header('sec-fetch-site') === 'same-origin') {
            $origin = $request->header('referer');
        }
        if (!$this->plugins->exposes('events') || !$this->origins->allows($origin)) {
            throw new RouteNotFound();
        }
    }

    /** @param array<string,mixed> $document */
    private function serialize(Request $request, string $format, string $mode, array $document): Response
    {
        return $this->cors($request, $this->api->render('events', 'event', $format, $mode, $document)
            ?? Response::json(['error' => ['code' => 'not_acceptable', 'message' => 'Unsupported format.']], 406));
    }

    private function cors(Request $request, Response $response): Response
    {
        $origin = $request->header('origin');
        return $origin === null || !$this->origins->allows($origin) ? $response : $response
            ->withHeader('Access-Control-Allow-Origin', $origin)->withHeader('Vary', 'Origin')
            ->withHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }
    /** @param array<string, mixed> $data */
    private function page(
        Request $request,
        SessionContext $session,
        User $user,
        string $title,
        string $view,
        array $data,
        int $status = 200,
    ): Response {
        $content = $this->views->render($view, [
            ...$data,
            'csrfToken' => $this->auth->csrf->token($session->token),
        ]);

        return $this->withSession($this->render($request, $user, $title, $content, $status), $session);
    }

    /** @return array{SessionContext, User}|Response */
    private function authorized(Request $request): array|Response
    {
        $session = $this->auth->sessions->start($request);
        $user = $session->record->userId === null
            ? null
            : $this->auth->users->findById($session->record->userId);
        if ($user === null || !$user->isActive()) {
            return $this->withSession($request->expectsJson()
                ? Response::json(['error' => ['code' => 'authentication_required']], 401)
                : Response::redirect('/login'), $session);
        }
        $allowed = $this->auth->plugins->find('events')?->state === 'enabled'
            && $this->auth->pluginAccess->allows($user->id, 'events');
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

    private function withSession(Response $response, SessionContext $session): Response
    {
        return $this->auth->sessions->withCookie($response, $session);
    }
}
