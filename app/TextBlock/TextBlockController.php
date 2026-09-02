<?php

declare(strict_types=1);

namespace ReaCms\TextBlock;

use ReaCms\Api\Policy\OriginAllowlist;
use ReaCms\Api\Template\PluginApiRenderer;
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
use ReaCms\Plugin\SafeHtml;
use ReaCms\Support\PlainText;
use Throwable;

final class TextBlockController
{
    public function __construct(
        private readonly TextBlockRepository $repository,
        private readonly PluginRouteGate $plugins,
        private readonly OriginAllowlist $origins,
        private readonly PluginApiRenderer $api,
        private readonly AuthServices $auth,
        private readonly ViewRenderer $views,
    ) {
    }

    public function collection(Request $request, string $format): Response
    {
        $this->requireApi($request);
        $blocks = $this->repository->all();

        return $this->serialize($request, $format, 'list', [
            'data' => array_map(static fn (TextBlock $block): array => $block->api(), $blocks),
            'meta' => ['total' => count($blocks)],
            'links' => ['self' => '/api/v1/text-block.' . rawurlencode($format)],
        ]);
    }

    public function item(Request $request, int $id, string $format): Response
    {
        $this->requireApi($request);
        $block = $id > 0 ? $this->repository->findById($id) : null;
        if ($block === null) {
            throw new RouteNotFound();
        }

        return $this->serialize($request, $format, 'detail', ['data' => $block->api()]);
    }

    public function named(Request $request, string $name, string $format): Response
    {
        $this->requireApi($request);
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $name) !== 1) {
            throw new RouteNotFound();
        }
        $block = $this->repository->findByName($name);
        if ($block === null) {
            throw new RouteNotFound();
        }

        return $this->serialize($request, $format, 'detail', ['data' => $block->api()]);
    }

    public function index(Request $request): Response
    {
        $context = $this->authorized($request);
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $query = $request->query()['q'] ?? '';
        $search = is_string($query) ? trim($query) : '';

        return $this->page($request, $session, $user, 'Text Blocks', 'cms/text-block/index', [
            'blocks' => $this->repository->all($search),
            'search' => $search,
            'message' => $this->queryString($request, 'message'),
        ]);
    }

    public function form(Request $request, ?int $id = null): Response
    {
        $context = $this->authorized($request);
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $block = $id === null ? null : $this->repository->findById($id);
        if ($id !== null && $block === null) {
            return $this->withSession(Response::redirect('/cms/text-block'), $session);
        }

        return $this->page(
            $request,
            $session,
            $user,
            $block === null ? 'New Text Block' : 'Edit Text Block',
            'cms/text-block/editor',
            ['block' => $block],
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
        try {
            $name = (new Slugger())->slug($form['name'] ?? '');
        } catch (Throwable) {
            $name = '';
        }
        $rawContent = $form['content'] ?? '';
        $content = SafeHtml::sanitize($rawContent)->value;
        if (
            $name === '' || strlen($rawContent) > 60_000
            || PlainText::fromHtml($content) === ''
        ) {
            return $this->failure(
                $request,
                $session,
                $user,
                422,
                'Text block incomplete',
                'cms/message',
                ['message' => 'A URL-safe name and text content are required.'],
            );
        }

        try {
            if ($id === null) {
                $this->repository->create($name, $content);
            } elseif ($this->repository->findById($id) !== null) {
                $this->repository->update($id, $name, $content);
            } else {
                return $this->withSession(Response::redirect('/cms/text-block'), $session);
            }
        } catch (TextBlockException $exception) {
            return $this->failure(
                $request,
                $session,
                $user,
                422,
                'Text block not saved',
                'cms/message',
                ['message' => $exception->getMessage()],
            );
        }

        return $this->withSession(Response::redirect(
            '/cms/text-block?message=' . rawurlencode('Text block saved.'),
        ), $session);
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
        $this->repository->delete($id);

        return $this->withSession(Response::redirect(
            '/cms/text-block?message=' . rawurlencode('Text block deleted.'),
        ), $session);
    }

    private function requireApi(Request $request): void
    {
        if (!$this->plugins->exposes('text_block') || !$this->origins->allows($request->header('origin'))) {
            throw new RouteNotFound();
        }
    }

    /** @param array<string, mixed> $document */
    private function serialize(Request $request, string $format, string $mode, array $document): Response
    {
        $response = $this->api->render('text_block', 'textBlock', $format, $mode, $document);
        if ($response === null) {
            return Response::json([
                'error' => ['code' => 'not_acceptable', 'message' => 'Unsupported format.'],
            ], 406);
        }
        $origin = $request->header('origin');

        return $origin === null ? $response : $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Vary', 'Origin')
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
    ): Response {
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
        $user = $session->record->userId === null
            ? null
            : $this->auth->users->findById($session->record->userId);
        if ($user === null || !$user->isActive()) {
            return $this->withSession(Response::redirect('/login'), $session);
        }
        $allowed = $this->auth->plugins->find('text_block')?->state === 'enabled'
            && $this->auth->pluginAccess->allows($user->id, 'text_block');
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

    private function queryString(Request $request, string $name): string
    {
        $value = $request->query()[$name] ?? '';

        return is_string($value) ? $value : '';
    }
}
