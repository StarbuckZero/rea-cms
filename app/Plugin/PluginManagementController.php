<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use ReaCms\Api\RateLimit\RateLimiter;
use ReaCms\Auth\AuthServices;
use ReaCms\Auth\SessionContext;
use ReaCms\Auth\User;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Theme\ThemePreference;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Support\Clock;
use Throwable;

final class PluginManagementController
{
    public function __construct(
        private readonly AuthServices $auth,
        private readonly ViewRenderer $views,
        private readonly PackageInspector $packages,
        private readonly PendingPackageStore $pending,
        private readonly PluginLifecycle $lifecycle,
        private readonly PluginDataManager $data,
        private readonly RateLimiter $rateLimiter,
        private readonly Clock $clock,
        private readonly string $stagingRoot,
    ) {
    }

    public function index(Request $request): Response
    {
        $context = $this->authorized($request, 'core.plugins.view');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        return $this->page($request, $session, $user, 'Plugin Management', 'admin/plugins/index', [
            'plugins' => $this->auth->plugins->all(),
            'success' => $this->successMessage($request),
            'error' => null,
            'canManage' => $this->auth->authorization->allows($user->id, 'core.plugins.manage'),
        ]);
    }

    public function inspect(Request $request): Response
    {
        $context = $this->authorized($request, 'core.plugins.manage');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        if (!$this->validCsrf($session, $request->form()['_csrf'] ?? null)) {
            return $this->csrfFailure($request, $session, $user, '/admin/plugins/inspect');
        }
        $rate = $this->rateLimiter->consume('plugin-upload', 'user:' . $user->id, 5, 3600);
        if (!$rate->allowed) {
            return $this->indexError(
                $request,
                $session,
                $user,
                'Too many plugin uploads. Try again later.',
                429,
                ['Retry-After' => (string) $rate->retryAfter],
            );
        }
        $upload = $request->file('plugin_zip');
        if (
            $upload === null || !$upload->isValid()
            || strtolower(pathinfo($upload->clientName, PATHINFO_EXTENSION)) !== 'zip'
        ) {
            return $this->indexError($request, $session, $user, 'Choose a valid plugin ZIP file.', 422);
        }

        try {
            $package = $this->packages->inspect($upload->temporaryPath, $this->stagingRoot);
            if ($this->auth->plugins->find($package->manifest->id) !== null) {
                throw new PluginException('That plugin ID is already installed. Uploads cannot overwrite it.');
            }
            $token = $this->pending->put($package, $session->record->tokenHash);
        } catch (PluginException $exception) {
            return $this->indexError($request, $session, $user, $exception->getMessage(), 422);
        }

        return $this->page($request, $session, $user, 'Confirm Plugin Installation', 'admin/plugins/preview', [
            'plugin' => $package->manifest,
            'packageHash' => $package->packageHash,
            'pendingToken' => $token,
            'migrations' => array_map(
                'basename',
                glob($package->directory . '/migrations/*.json') ?: [],
            ),
            'recentlyReauthenticated' => $this->recentlyReauthenticated($session),
        ]);
    }

    public function install(Request $request): Response
    {
        $context = $this->authorized($request, 'core.plugins.manage');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $form = $request->form();
        if (!$this->validCsrf($session, $form['_csrf'] ?? null)) {
            return $this->csrfFailure($request, $session, $user, '/admin/plugins/install');
        }
        if (($form['confirm_install'] ?? '') !== '1' || !$this->recentlyReauthenticated($session)) {
            return $this->indexError(
                $request,
                $session,
                $user,
                'Installation requires explicit confirmation and a reauthentication within the last 10 minutes.',
                422,
            );
        }
        try {
            $package = $this->pending->take($form['pending_token'] ?? '', $session->record->tokenHash);
            $this->lifecycle->install(
                $package,
                $user->id,
                $request->clientIp(),
                $request->requestId(),
            );
        } catch (PluginException $exception) {
            return $this->indexError($request, $session, $user, $exception->getMessage(), 422);
        }
        return $this->redirect($session, '/admin/plugins?result=installed');
    }

    public function enable(Request $request, string $pluginId): Response
    {
        return $this->changeState($request, $pluginId, true);
    }

    public function disable(Request $request, string $pluginId): Response
    {
        return $this->changeState($request, $pluginId, false);
    }

    public function removal(Request $request, string $pluginId): Response
    {
        $context = $this->authorized($request, 'core.plugins.manage');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $plugin = $this->auth->plugins->find($pluginId);
        if ($plugin === null) {
            return $this->indexError($request, $session, $user, 'The plugin is not installed.', 404);
        }
        try {
            $summary = $this->data->summarize($plugin);
        } catch (Throwable) {
            return $this->indexError(
                $request,
                $session,
                $user,
                'The plugin data could not be inspected, so removal was stopped.',
                409,
            );
        }
        return $this->page($request, $session, $user, 'Remove ' . $plugin->name, 'admin/plugins/remove', [
            'plugin' => $plugin,
            'summary' => $summary,
            'canPurge' => $this->auth->authorization->allows($user->id, 'core.plugins.purge'),
            'recentlyReauthenticated' => $this->recentlyReauthenticated($session),
        ]);
    }

    public function backup(Request $request, string $pluginId): Response
    {
        $context = $this->authorized($request, 'core.plugins.purge');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        if (!$this->validCsrf($session, $request->form()['_csrf'] ?? null)) {
            return $this->csrfFailure($request, $session, $user, '/admin/plugins/' . $pluginId . '/backup');
        }
        $plugin = $this->auth->plugins->find($pluginId);
        if ($plugin === null) {
            return $this->indexError($request, $session, $user, 'The plugin is not installed.', 404);
        }
        try {
            $path = $this->data->export($plugin);
            $contents = file_get_contents($path);
            if (!is_string($contents)) {
                throw new PluginException('The completed backup could not be read.');
            }
        } catch (Throwable) {
            return $this->indexError($request, $session, $user, 'The plugin data backup failed.', 500);
        }
        $this->auth->audit->record(
            'plugin.backup_exported',
            $user->id,
            $request->clientIp(),
            $request->requestId(),
            ['file' => basename($path)],
            'plugin',
            $plugin->id,
        );
        return $this->auth->sessions->withCookie(
            new Response($contents, 200, [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="rea-plugin-' . $plugin->id . '-backup.json"',
                'Cache-Control' => 'no-store, private',
            ]),
            $session,
        );
    }

    public function uninstall(Request $request, string $pluginId): Response
    {
        $context = $this->authorized($request, 'core.plugins.manage');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        $form = $request->form();
        if (!$this->validCsrf($session, $form['_csrf'] ?? null)) {
            return $this->csrfFailure($request, $session, $user, '/admin/plugins/' . $pluginId . '/uninstall');
        }
        if (!$this->recentlyReauthenticated($session)) {
            return $this->indexError($request, $session, $user, 'Removal requires recent reauthentication.', 422);
        }
        $plugin = $this->auth->plugins->find($pluginId);
        if ($plugin === null) {
            return $this->indexError($request, $session, $user, 'The plugin is not installed.', 404);
        }
        $mode = $form['mode'] ?? '';
        try {
            if ($mode === 'keep_data') {
                if (!hash_equals('REMOVE ' . $pluginId, $form['confirmation'] ?? '')) {
                    throw new PluginException('Type the required removal confirmation exactly.');
                }
                if ($plugin->state !== 'uninstalled') {
                    $this->lifecycle->uninstall(
                        $pluginId,
                        $user->id,
                        $request->clientIp(),
                        $request->requestId(),
                    );
                }
                return $this->redirect($session, '/admin/plugins?result=removed');
            }
            if ($mode !== 'delete_data') {
                throw new PluginException('Choose a valid plugin removal option.');
            }
            if (!$this->auth->authorization->allows($user->id, 'core.plugins.purge')) {
                return $this->forbidden($request, $session, $user, 'core.plugins.purge');
            }
            if ($plugin->state !== 'uninstalled') {
                $this->lifecycle->uninstall(
                    $pluginId,
                    $user->id,
                    $request->clientIp(),
                    $request->requestId(),
                );
            }
            $this->lifecycle->purge(
                $pluginId,
                $form['confirmation'] ?? '',
                fn (PluginRecord $record): string => $this->data->export($record),
                fn (PluginRecord $record) => $this->data->purge($record),
                $user->id,
                $request->clientIp(),
                $request->requestId(),
            );
        } catch (PluginException $exception) {
            return $this->indexError($request, $session, $user, $exception->getMessage(), 422);
        } catch (Throwable) {
            return $this->indexError(
                $request,
                $session,
                $user,
                'Removal failed safely. Plugin data was not intentionally deleted.',
                500,
            );
        }
        return $this->redirect($session, '/admin/plugins?result=purged');
    }

    private function changeState(Request $request, string $pluginId, bool $enable): Response
    {
        $context = $this->authorized($request, 'core.plugins.manage');
        if ($context instanceof Response) {
            return $context;
        }
        [$session, $user] = $context;
        if (!$this->validCsrf($session, $request->form()['_csrf'] ?? null)) {
            return $this->csrfFailure($request, $session, $user, $request->path());
        }
        try {
            $method = $enable ? 'enable' : 'disable';
            $this->lifecycle->{$method}(
                $pluginId,
                $user->id,
                $request->clientIp(),
                $request->requestId(),
            );
        } catch (PluginException $exception) {
            return $this->indexError($request, $session, $user, $exception->getMessage(), 422);
        }
        return $this->redirect($session, '/admin/plugins?result=' . ($enable ? 'enabled' : 'disabled'));
    }

    /** @return array{SessionContext, User}|Response */
    private function authorized(Request $request, string $permission): array|Response
    {
        $session = $this->auth->sessions->start($request);
        $user = $session->record->userId === null
            ? null
            : $this->auth->users->findById($session->record->userId);
        if ($user === null || !$user->isActive()) {
            return $this->auth->sessions->withCookie(Response::redirect('/login'), $session);
        }
        if (
            !$this->auth->authorization->allows($user->id, 'core.admin.access')
            || !$this->auth->authorization->allows($user->id, $permission)
        ) {
            return $this->forbidden($request, $session, $user, $permission);
        }
        return [$session, $user];
    }

    private function recentlyReauthenticated(SessionContext $session): bool
    {
        $reauthenticated = $session->record->reauthenticatedAt;
        return $reauthenticated !== null
            && $reauthenticated >= $this->clock->now()->modify('-10 minutes');
    }

    private function validCsrf(SessionContext $session, ?string $token): bool
    {
        return $this->auth->csrf->validate($session->token, $token);
    }

    private function successMessage(Request $request): ?string
    {
        $result = $request->query()['result'] ?? null;
        return is_string($result) ? match ($result) {
            'installed' => 'The plugin was installed and is disabled until you enable it.',
            'enabled' => 'The plugin was enabled.',
            'disabled' => 'The plugin was disabled. Its files and data were preserved.',
            'removed' => 'The plugin was removed from service. Its data and a private copy of its files '
                . 'were preserved.',
            'purged' => 'The plugin was removed and its data was deleted after a final backup was created.',
            default => null,
        } : null;
    }

    /** @param array<string, string> $headers */
    private function indexError(
        Request $request,
        SessionContext $session,
        User $user,
        string $message,
        int $status,
        array $headers = [],
    ): Response {
        $response = $this->page($request, $session, $user, 'Plugin Management', 'admin/plugins/index', [
            'plugins' => $this->auth->plugins->all(),
            'success' => null,
            'error' => $message,
            'canManage' => $this->auth->authorization->allows($user->id, 'core.plugins.manage'),
        ], $status);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }

    private function csrfFailure(Request $request, SessionContext $session, User $user, string $route): Response
    {
        $this->auth->audit->record(
            'auth.csrf_failed',
            $user->id,
            $request->clientIp(),
            $request->requestId(),
            ['route' => $route],
        );
        return $this->page(
            $request,
            $session,
            $user,
            'Invalid request',
            'errors/csrf',
            [],
            419,
        );
    }

    private function forbidden(
        Request $request,
        SessionContext $session,
        User $user,
        string $permission,
    ): Response {
        $this->auth->audit->record(
            'authorization.denied',
            $user->id,
            $request->clientIp(),
            $request->requestId(),
            ['permission' => $permission],
        );
        return $this->page($request, $session, $user, 'Access denied', 'errors/forbidden', [], 403);
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
        $response = Response::html($this->views->render('layouts/base', [
            'title' => $title,
            'theme' => ThemePreference::parse($user->theme),
            'content' => $content,
            'authenticatedUser' => $user,
            'csrfToken' => $this->auth->csrf->token($session->token),
            'canAccessAdmin' => true,
            'canManagePlugins' => true,
            'pluginNavigation' => (new PluginNavigation(
                $this->auth->plugins,
                $this->auth->pluginAccess,
            ))->forUser($user->id),
        ]), $status)->withHeader('Cache-Control', 'no-store, private');
        return $this->auth->sessions->withCookie($response, $session);
    }

    private function redirect(SessionContext $session, string $location): Response
    {
        return $this->auth->sessions->withCookie(Response::redirect($location), $session);
    }
}
