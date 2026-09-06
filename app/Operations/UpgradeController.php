<?php

declare(strict_types=1);

namespace ReaCms\Operations;

use ReaCms\Auth\AuthServices;
use ReaCms\Auth\RoleLookup;
use ReaCms\Auth\SessionContext;
use ReaCms\Auth\User;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Theme\ThemePreference;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Database\Migrations\CoreMigrationPlan;
use ReaCms\Database\Migrations\CoreMigrationRunner;
use ReaCms\Plugin\PluginNavigation;
use RuntimeException;
use Throwable;

final class UpgradeController
{
    public function __construct(
        private readonly AuthServices $services,
        private readonly RoleLookup $roles,
        private readonly ViewRenderer $views,
        private readonly CoreMigrationRunner $migrations,
        private readonly string $version,
        private readonly string $lockPath,
    ) {
    }

    public function index(Request $request): Response
    {
        [$session, $user] = $this->authenticated($request);
        if ($user === null) {
            return $this->services->sessions->withCookie(Response::redirect('/login'), $session);
        }
        if (!$this->roles->hasRole($user->id, 'super-administrator')) {
            return $this->forbidden($request, $session, $user);
        }

        try {
            $plan = $this->migrations->plan();
        } catch (Throwable) {
            return $this->render(
                $request,
                $session,
                $user,
                new CoreMigrationPlan([], []),
                null,
                'The migration integrity check failed. Do not continue until the release files '
                    . 'and database are verified.',
                409,
                false,
            );
        }

        $upgraded = ($request->query()['upgraded'] ?? null) === '1';

        return $this->render(
            $request,
            $session,
            $user,
            $plan,
            $upgraded ? 'The database upgrade completed successfully.' : null,
        );
    }

    public function apply(Request $request): Response
    {
        [$session, $user] = $this->authenticated($request);
        if ($user === null) {
            return $this->services->sessions->withCookie(Response::redirect('/login'), $session);
        }
        if (!$this->roles->hasRole($user->id, 'super-administrator')) {
            return $this->forbidden($request, $session, $user);
        }

        $form = $request->form();
        if (!$this->services->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            return $this->renderWithPlan(
                $request,
                $session,
                $user,
                null,
                'Your form expired. Please try again.',
                419,
            );
        }
        if (($form['backup_confirmed'] ?? '') !== '1') {
            return $this->renderWithPlan(
                $request,
                $session,
                $user,
                null,
                'Confirm that you created and verified a current database backup.',
                422,
            );
        }

        try {
            $this->services->login->reauthenticate($user, $form['password'] ?? '');
        } catch (RuntimeException) {
            $this->services->audit->record(
                'system.upgrade_reauthentication_failed',
                $user->id,
                $request->clientIp(),
                $request->requestId(),
            );

            return $this->renderWithPlan(
                $request,
                $session,
                $user,
                null,
                'The current password is incorrect.',
                422,
            );
        }

        $lock = fopen($this->lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            return $this->renderWithPlan(
                $request,
                $session,
                $user,
                null,
                'Another upgrade is already running. Wait for it to finish and refresh this page.',
                409,
            );
        }
        chmod($this->lockPath, 0600);

        try {
            $migrated = $this->migrations->migrate();
            $this->services->sessions->markReauthenticated($session);
            $this->services->audit->record(
                'system.upgrade_completed',
                $user->id,
                $request->clientIp(),
                $request->requestId(),
                [
                    'version' => $this->version,
                    'migration_count' => count($migrated),
                    'migrations' => implode(',', $migrated),
                ],
            );
        } catch (Throwable) {
            return $this->renderWithPlan(
                $request,
                $session,
                $user,
                null,
                'The upgrade stopped before completion. Keep the site in maintenance mode and check the server log.',
                500,
                false,
            );
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $this->services->sessions->withCookie(Response::redirect('/admin/upgrade?upgraded=1'), $session);
    }

    /**
     * @return array{SessionContext, User|null}
     */
    private function authenticated(Request $request): array
    {
        $session = $this->services->sessions->start($request);
        $user = $session->record->userId === null
            ? null
            : $this->services->users->findById($session->record->userId);

        if ($user !== null && !$user->isActive()) {
            $this->services->sessions->revoke($session);

            return [$session, null];
        }

        return [$session, $user];
    }

    private function forbidden(Request $request, SessionContext $session, User $user): Response
    {
        $this->services->audit->record(
            'authorization.denied',
            $user->id,
            $request->clientIp(),
            $request->requestId(),
            ['role' => 'super-administrator', 'route' => '/admin/upgrade'],
        );

        return $this->services->sessions->withCookie($this->page(
            $request,
            $session,
            $user,
            'Access denied',
            $this->views->render('errors/forbidden'),
            403,
        ), $session);
    }

    private function renderWithPlan(
        Request $request,
        SessionContext $session,
        User $user,
        ?string $success,
        ?string $error,
        int $status,
        bool $canRun = true,
    ): Response {
        try {
            $plan = $this->migrations->plan();
        } catch (Throwable) {
            $plan = new CoreMigrationPlan([], []);
            $canRun = false;
        }

        return $this->render($request, $session, $user, $plan, $success, $error, $status, $canRun);
    }

    private function render(
        Request $request,
        SessionContext $session,
        User $user,
        CoreMigrationPlan $plan,
        ?string $success = null,
        ?string $error = null,
        int $status = 200,
        bool $canRun = true,
    ): Response {
        $content = $this->views->render('admin/upgrade', [
            'version' => $this->version,
            'plan' => $plan,
            'csrfToken' => $this->services->csrf->token($session->token),
            'success' => $success,
            'error' => $error,
            'canRun' => $canRun,
        ]);

        return $this->services->sessions->withCookie($this->page(
            $request,
            $session,
            $user,
            'Upgrade Rea CMS',
            $content,
            $status,
        ), $session);
    }

    private function page(
        Request $request,
        SessionContext $session,
        User $user,
        string $title,
        string $content,
        int $status,
    ): Response {
        return Response::html($this->views->render('layouts/base', [
            'title' => $title,
            'theme' => ThemePreference::parse($user->theme),
            'content' => $content,
            'authenticatedUser' => $user,
            'csrfToken' => $this->services->csrf->token($session->token),
            'canAccessAdmin' => $this->services->authorization->allows($user->id, 'core.admin.access'),
            'canManagePlugins' => $this->services->authorization->allows($user->id, 'core.plugins.view'),
            'pluginNavigation' => (new PluginNavigation(
                $this->services->plugins,
                $this->services->pluginAccess,
            ))->forUser($user->id),
        ]), $status)
            ->withHeader('Cache-Control', 'no-store, private')
            ->withHeader('Pragma', 'no-cache');
    }
}
