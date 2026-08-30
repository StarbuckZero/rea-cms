<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Theme\ThemePreference;
use ReaCms\Core\View\ViewRenderer;
use RuntimeException;

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
            return $this->services->sessions->withCookie(Response::redirect('/admin'), $session);
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

        return $this->services->sessions->withCookie(Response::redirect('/admin'), $authenticated);
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
        ]);

        return $this->services->sessions->withCookie(
            $this->renderPage($request, 'Administration', $content, authenticatedUser: $user),
            $session,
        );
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
            'theme' => ThemePreference::parse($request->cookie('rea_theme')),
            'content' => $content,
            'publicHomepage' => false,
            'authenticatedUser' => $authenticatedUser,
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
