<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Theme\ThemePreference;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Plugin\PluginNavigation;
use RuntimeException;
use ReaCms\Plugin\PluginRecord;

final class AuthController
{
    public function __construct(
        private readonly AuthServices $services,
        private readonly ViewRenderer $views,
    ) {
    }

    public function loginForm(Request $request): Response
    {
        $session = $this->services->sessions->start($request);

        if ($session->isAuthenticated()) {
            return $this->services->sessions->withCookie(Response::redirect('/dashboard'), $session);
        }

        return $this->services->sessions->withCookie($this->renderLogin(
            $request,
            $this->services->csrf->token($session->token),
        ), $session);
    }

    public function login(Request $request): Response
    {
        $session = $this->services->sessions->start($request);
        $form = $request->form();

        if (!$this->services->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            $this->audit($request, 'auth.csrf_failed', $session->record->userId, ['route' => '/login']);

            return $this->services->sessions->withCookie($this->renderLogin(
                $request,
                $this->services->csrf->token($session->token),
                'Your form expired. Please try again.',
                419,
            ), $session);
        }

        $result = $this->services->login->authenticate(
            $form['email'] ?? '',
            $form['password'] ?? '',
            $request->clientIp(),
        );

        if (!$result->succeeded()) {
            $this->audit($request, 'auth.login_failed', null, ['locked' => $result->locked]);

            return $this->services->sessions->withCookie($this->renderLogin(
                $request,
                $this->services->csrf->token($session->token),
                $result->locked
                    ? 'Too many attempts. Try again later.'
                    : 'The email or password is incorrect.',
                $result->locked ? 429 : 422,
            ), $session);
        }

        $user = $result->user;
        if ($user === null) {
            throw new RuntimeException('A successful login result must contain a user.');
        }

        $authenticated = $this->services->sessions->rotate($request, $session, $user->id);
        $this->audit($request, 'auth.login_succeeded', $user->id);

        return $this->services->sessions->withCookie(Response::redirect('/dashboard'), $authenticated);
    }

    public function dashboard(Request $request): Response
    {
        [$session, $user] = $this->authenticated($request);

        if ($user === null) {
            $response = Response::redirect('/login');

            return $session->record->userId === null
                ? $this->services->sessions->withCookie($response, $session)
                : $this->services->sessions->clearCookie($response);
        }

        $content = $this->views->render('pages/dashboard', [
            'user' => $user,
            'plugins' => array_values(array_filter(
                $this->services->plugins->active(),
                fn ($plugin): bool => $this->services->pluginAccess->allows($user->id, $plugin->id),
            )),
        ]);

        return $this->services->sessions->withCookie(
            $this->renderPage($request, 'Dashboard', $content, authenticatedUser: $user),
            $session,
        );
    }

    public function profile(Request $request): Response
    {
        [$session, $user] = $this->authenticated($request);
        if ($user === null) {
            return $this->services->sessions->withCookie(Response::redirect('/login'), $session);
        }

        return $this->renderProfile($request, $session, $user);
    }

    public function updateProfile(Request $request): Response
    {
        [$session, $user] = $this->authenticated($request);
        if ($user === null) {
            return $this->services->sessions->withCookie(Response::redirect('/login'), $session);
        }
        $form = $request->form();
        if (!$this->services->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            return $this->csrfFailure($request, $session, $user, '/profile');
        }

        $displayName = trim($form['display_name'] ?? '');
        $theme = $form['theme'] ?? null;
        if (!$this->validDisplayName($displayName)) {
            return $this->renderProfile(
                $request,
                $session,
                $user,
                profileError: 'Enter a display name between 1 and 191 characters without control characters.',
                status: 422,
            );
        }
        if (!ThemePreference::accepts($theme)) {
            return $this->renderProfile(
                $request,
                $session,
                $user,
                profileError: 'Choose one of the available themes.',
                status: 422,
            );
        }

        $this->services->users->updateProfile($user->id, $displayName, ThemePreference::parse($theme));
        $this->audit($request, 'profile.updated', $user->id, ['theme' => ThemePreference::parse($theme)]);

        return $this->services->sessions->withCookie(Response::redirect('/profile?saved=profile'), $session);
    }

    public function updateProfilePassword(Request $request): Response
    {
        [$session, $user] = $this->authenticated($request);
        if ($user === null) {
            return $this->services->sessions->withCookie(Response::redirect('/login'), $session);
        }
        $form = $request->form();
        if (!$this->services->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            return $this->csrfFailure($request, $session, $user, '/profile/password');
        }

        $currentPassword = $form['current_password'] ?? '';
        $newPassword = $form['new_password'] ?? '';
        $confirmation = $form['password_confirmation'] ?? '';
        if (strlen($newPassword) < 12) {
            return $this->renderProfile(
                $request,
                $session,
                $user,
                passwordError: 'The new password must contain at least 12 characters.',
                status: 422,
            );
        }
        if (!hash_equals($newPassword, $confirmation)) {
            return $this->renderProfile(
                $request,
                $session,
                $user,
                passwordError: 'The new password and confirmation do not match.',
                status: 422,
            );
        }
        if (hash_equals($currentPassword, $newPassword)) {
            return $this->renderProfile(
                $request,
                $session,
                $user,
                passwordError: 'Choose a new password that is different from your current password.',
                status: 422,
            );
        }

        try {
            $this->services->login->reauthenticate($user, $currentPassword);
        } catch (RuntimeException) {
            $this->audit($request, 'profile.password_change_failed', $user->id);
            return $this->renderProfile(
                $request,
                $session,
                $user,
                passwordError: 'The current password is incorrect.',
                status: 422,
            );
        }

        $this->services->users->updatePassword($user->id, $this->services->login->hashPassword($newPassword));
        $this->services->sessions->markReauthenticated($session);
        $this->audit($request, 'profile.password_changed', $user->id);

        return $this->services->sessions->withCookie(Response::redirect('/profile?saved=password'), $session);
    }

    public function admin(Request $request): Response
    {
        [$session, $user] = $this->authenticated($request);

        if ($user === null) {
            $response = Response::redirect('/login');

            return $session->record->userId === null
                ? $this->services->sessions->withCookie($response, $session)
                : $this->services->sessions->clearCookie($response);
        }

        if (!$this->services->authorization->allows($user->id, 'core.admin.access')) {
            $this->audit($request, 'authorization.denied', $user->id, ['permission' => 'core.admin.access']);

            return $this->services->sessions->withCookie($this->renderPage(
                $request,
                'Access denied',
                $this->views->render('errors/forbidden'),
                403,
                $user,
            ), $session);
        }

        $content = $this->views->render('admin/dashboard', [
            'user' => $user,
            'csrfToken' => $this->services->csrf->token($session->token),
            'reauthenticatedAt' => $session->record->reauthenticatedAt?->format(DATE_ATOM),
            'sessions' => $this->services->sessions->listForUser($user->id),
            'currentSessionHash' => $session->record->tokenHash,
            'users' => $this->services->users->all(),
            'plugins' => [...$this->services->plugins->active(), new PluginRecord(
                'media',
                '1.0.0',
                'enabled',
                '',
                'Media',
                'Shared images and files.',
            )],
            'pluginAccess' => $this->services->pluginAccess,
            'canManagePlugins' => $this->services->authorization->allows($user->id, 'core.plugins.view'),
        ]);

        return $this->services->sessions->withCookie(
            $this->renderPage($request, 'Administration', $content, authenticatedUser: $user),
            $session,
        );
    }

    public function createUser(Request $request): Response
    {
        [$session, $admin] = $this->requireAdministrator($request);
        if ($admin === null) {
            return $this->admin($request);
        }
        $form = $request->form();
        if (!$this->services->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            return $this->csrfFailure($request, $session, $admin, '/admin/users');
        }
        $email = strtolower(trim($form['email'] ?? ''));
        $name = trim($form['display_name'] ?? '');
        $password = $form['password'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($password) < 12) {
            return $this->services->sessions->withCookie(Response::redirect('/admin?user_error=1'), $session);
        }
        $userId = $this->services->users->create($email, $this->services->login->hashPassword($password), $name);
        $this->services->pluginAccess->replaceForUser($userId, $this->selectedPlugins($form));
        $this->audit($request, 'admin.user_created', $admin->id, ['user_id' => $userId]);
        return $this->services->sessions->withCookie(Response::redirect('/admin'), $session);
    }

    public function updateUser(Request $request, int $userId): Response
    {
        [$session, $admin] = $this->requireAdministrator($request);
        if ($admin === null) {
            return $this->admin($request);
        }
        $form = $request->form();
        if (!$this->services->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            return $this->csrfFailure($request, $session, $admin, '/admin/users/' . $userId);
        }
        $status = ($form['status'] ?? '') === 'active' ? 'active' : 'disabled';
        $this->services->users->update($userId, $form['email'] ?? '', $form['display_name'] ?? '', $status);
        if (($form['password'] ?? '') !== '') {
            $this->services->users->updatePassword($userId, $this->services->login->hashPassword($form['password']));
        }
        $this->services->pluginAccess->replaceForUser($userId, $this->selectedPlugins($form));
        $this->audit($request, 'admin.user_updated', $admin->id, ['user_id' => $userId]);
        return $this->services->sessions->withCookie(Response::redirect('/admin'), $session);
    }

    public function deleteUser(Request $request, int $userId): Response
    {
        [$session, $admin] = $this->requireAdministrator($request);
        if ($admin === null) {
            return $this->admin($request);
        }
        $form = $request->form();
        if (!$this->services->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            return $this->csrfFailure($request, $session, $admin, '/admin/users/' . $userId . '/delete');
        }
        if ($admin->id !== $userId) {
            $this->services->users->delete($userId);
            $this->audit($request, 'admin.user_deleted', $admin->id, ['user_id' => $userId]);
        }
        return $this->services->sessions->withCookie(Response::redirect('/admin'), $session);
    }

    public function logout(Request $request): Response
    {
        [$session, $user] = $this->authenticated($request);
        $form = $request->form();

        if (!$this->services->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            $this->audit($request, 'auth.csrf_failed', $user?->id, ['route' => '/logout']);

            return $this->services->sessions->withCookie($this->renderPage(
                $request,
                'Invalid request',
                $this->views->render('errors/csrf'),
                419,
                $user,
            ), $session);
        }

        $this->services->sessions->revoke($session);
        $this->audit($request, 'auth.logout', $user?->id);

        return $this->services->sessions->clearCookie(Response::redirect('/login'));
    }

    public function reauthenticate(Request $request): Response
    {
        [$session, $user] = $this->authenticated($request);

        if ($user === null) {
            return $this->services->sessions->withCookie(Response::redirect('/login'), $session);
        }

        $form = $request->form();
        if (!$this->services->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            $this->audit($request, 'auth.csrf_failed', $user->id, ['route' => '/admin/reauthenticate']);

            return $this->services->sessions->withCookie($this->renderPage(
                $request,
                'Invalid request',
                $this->views->render('errors/csrf'),
                419,
                $user,
            ), $session);
        }

        try {
            $this->services->login->reauthenticate($user, $form['password'] ?? '');
        } catch (RuntimeException) {
            $this->audit($request, 'auth.reauthentication_failed', $user->id);

            return $this->services->sessions->withCookie($this->renderPage(
                $request,
                'Administration',
                $this->views->render('admin/reauthentication-failed'),
                422,
                $user,
            ), $session);
        }

        $this->services->sessions->markReauthenticated($session);
        $this->audit($request, 'auth.reauthentication_succeeded', $user->id);

        return $this->services->sessions->withCookie(Response::redirect('/admin'), $session);
    }

    public function forgotPasswordForm(Request $request): Response
    {
        $session = $this->services->sessions->start($request);

        return $this->services->sessions->withCookie($this->renderPage(
            $request,
            'Reset password',
            $this->views->render('auth/forgot-password', [
                'csrfToken' => $this->services->csrf->token($session->token),
                'sent' => false,
            ]),
        ), $session);
    }

    public function revokeSession(Request $request): Response
    {
        [$session, $user] = $this->authenticated($request);
        if ($user === null) {
            return $this->services->sessions->withCookie(Response::redirect('/login'), $session);
        }

        $form = $request->form();
        if (!$this->services->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            $this->audit($request, 'auth.csrf_failed', $user->id, ['route' => '/admin/sessions/revoke']);

            return $this->services->sessions->withCookie($this->renderPage(
                $request,
                'Invalid request',
                $this->views->render('errors/csrf'),
                419,
                $user,
            ), $session);
        }

        $tokenHash = $form['session'] ?? '';
        $revoked = $this->services->sessions->revokeForUser($user->id, $tokenHash);
        $this->audit($request, 'auth.session_revoked', $user->id, ['revoked' => $revoked]);

        if ($revoked && hash_equals($session->record->tokenHash, $tokenHash)) {
            return $this->services->sessions->clearCookie(Response::redirect('/login'));
        }

        return $this->services->sessions->withCookie(Response::redirect('/admin'), $session);
    }

    public function forgotPassword(Request $request): Response
    {
        $session = $this->services->sessions->start($request);
        $form = $request->form();

        if (!$this->services->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            $this->audit($request, 'auth.csrf_failed', null, ['route' => '/forgot-password']);

            return $this->services->sessions->withCookie($this->renderPage(
                $request,
                'Invalid request',
                $this->views->render('errors/csrf'),
                419,
            ), $session);
        }

        $this->services->passwordReset->request($form['email'] ?? '');
        $this->audit($request, 'auth.password_reset_requested', null);

        return $this->services->sessions->withCookie($this->renderPage(
            $request,
            'Reset password',
            $this->views->render('auth/forgot-password', [
                'csrfToken' => $this->services->csrf->token($session->token),
                'sent' => true,
            ]),
        ), $session);
    }

    public function resetPasswordForm(Request $request): Response
    {
        $session = $this->services->sessions->start($request);

        return $this->services->sessions->withCookie($this->renderPage(
            $request,
            'Choose a new password',
            $this->views->render('auth/reset-password', [
                'csrfToken' => $this->services->csrf->token($session->token),
                'email' => '',
                'token' => '',
                'error' => null,
            ]),
        ), $session);
    }

    public function resetPassword(Request $request): Response
    {
        $session = $this->services->sessions->start($request);
        $form = $request->form();

        if (!$this->services->csrf->validate($session->token, $form['_csrf'] ?? null)) {
            $this->audit($request, 'auth.csrf_failed', null, ['route' => '/reset-password']);

            return $this->services->sessions->withCookie($this->renderPage(
                $request,
                'Invalid request',
                $this->views->render('errors/csrf'),
                419,
            ), $session);
        }

        $password = $form['password'] ?? '';
        $confirmed = $form['password_confirmation'] ?? '';
        $reset = hash_equals($password, $confirmed) && $this->services->passwordReset->reset(
            $form['email'] ?? '',
            $form['token'] ?? '',
            $password,
        );

        if (!$reset) {
            $this->audit($request, 'auth.password_reset_failed', null);

            return $this->services->sessions->withCookie($this->renderPage(
                $request,
                'Choose a new password',
                $this->views->render('auth/reset-password', [
                    'csrfToken' => $this->services->csrf->token($session->token),
                    'email' => $form['email'] ?? '',
                    'token' => '',
                    'error' => 'The reset link is invalid or the password does not meet the requirements.',
                ]),
                422,
            ), $session);
        }

        $this->audit($request, 'auth.password_reset_succeeded', null);

        return $this->services->sessions->withCookie(Response::redirect('/login?reset=1'), $session);
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
            $this->audit($request, 'auth.inactive_session_rejected', $user->id, ['status' => $user->status]);

            return [$session, null];
        }

        return [$session, $user];
    }

    /** @return array{SessionContext, User|null} */
    private function requireAdministrator(Request $request): array
    {
        [$session, $user] = $this->authenticated($request);
        if ($user === null || !$this->services->authorization->allows($user->id, 'core.admin.access')) {
            return [$session, null];
        }
        return [$session, $user];
    }

    /** @param array<string, string> $form
     * @return list<string>
     */
    private function selectedPlugins(array $form): array
    {
        $selected = [];
        foreach ($this->services->plugins->active() as $plugin) {
            if (($form['plugin_' . $plugin->id] ?? '') === '1') {
                $selected[] = $plugin->id;
            }
        }
        if (($form['plugin_media'] ?? '') === '1') {
            $selected[] = 'media';
        }
        return $selected;
    }

    private function csrfFailure(Request $request, SessionContext $session, User $user, string $route): Response
    {
        $this->audit($request, 'auth.csrf_failed', $user->id, ['route' => $route]);
        return $this->services->sessions->withCookie($this->renderPage(
            $request,
            'Invalid request',
            $this->views->render('errors/csrf'),
            419,
            $user,
        ), $session);
    }

    private function renderProfile(
        Request $request,
        SessionContext $session,
        User $user,
        ?string $profileError = null,
        ?string $passwordError = null,
        int $status = 200,
    ): Response {
        $saved = $request->query()['saved'] ?? null;
        $success = is_string($saved) ? match ($saved) {
            'profile' => 'Your profile settings were saved.',
            'password' => 'Your password was changed.',
            default => null,
        } : null;
        $content = $this->views->render('auth/profile', [
            'user' => $user,
            'csrfToken' => $this->services->csrf->token($session->token),
            'themes' => ThemePreference::choices(),
            'success' => $success,
            'profileError' => $profileError,
            'passwordError' => $passwordError,
        ]);

        return $this->services->sessions->withCookie(
            $this->renderPage($request, 'User Profile', $content, $status, $user),
            $session,
        );
    }

    private function validDisplayName(string $displayName): bool
    {
        $length = mb_strlen($displayName);
        return $length >= 1 && $length <= 191 && preg_match('/[\x00-\x1F\x7F]/u', $displayName) !== 1;
    }

    private function renderLogin(
        Request $request,
        string $csrfToken,
        ?string $error = null,
        int $status = 200,
    ): Response {
        return $this->renderPage($request, 'Sign in', $this->views->render('auth/login', [
            'csrfToken' => $csrfToken,
            'error' => $error,
        ]), $status);
    }

    private function renderPage(
        Request $request,
        string $title,
        string $content,
        int $status = 200,
        ?User $authenticatedUser = null,
    ): Response {
        return Response::html($this->views->render('layouts/base', [
            'title' => $title,
            'theme' => ThemePreference::parse(
                $authenticatedUser === null ? $request->cookie('rea_theme') : $authenticatedUser->theme,
            ),
            'content' => $content,
            'authenticatedUser' => $authenticatedUser,
            'csrfToken' => $authenticatedUser === null ? null : $this->services->csrf->token(
                $this->services->sessions->start($request)->token,
            ),
            'canAccessAdmin' => $authenticatedUser !== null
                && $this->services->authorization->allows($authenticatedUser->id, 'core.admin.access'),
            'canManagePlugins' => $authenticatedUser !== null
                && $this->services->authorization->allows($authenticatedUser->id, 'core.admin.access')
                && $this->services->authorization->allows($authenticatedUser->id, 'core.plugins.view'),
            'pluginNavigation' => $authenticatedUser === null ? [] : (new PluginNavigation(
                $this->services->plugins,
                $this->services->pluginAccess,
            ))->forUser($authenticatedUser->id),
        ]), $status)
            ->withHeader('Cache-Control', 'no-store, private')
            ->withHeader('Pragma', 'no-cache');
    }

    /**
     * @param array<string, bool|int|float|string|null> $metadata
     */
    private function audit(
        Request $request,
        string $event,
        ?int $actorUserId,
        array $metadata = [],
    ): void {
        $this->services->audit->record(
            $event,
            $actorUserId,
            $request->clientIp(),
            $request->requestId(),
            $metadata,
        );
    }
}
