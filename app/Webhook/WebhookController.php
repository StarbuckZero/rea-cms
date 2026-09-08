<?php

declare(strict_types=1);

namespace ReaCms\Webhook;

use ReaCms\Auth\AuthServices;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Theme\ThemePreference;
use ReaCms\Core\View\ViewRenderer;
use ReaCms\Plugin\PluginNavigation;

final class WebhookController
{
    public function __construct(
        private readonly AuthServices $auth,
        private readonly ViewRenderer $views,
        private readonly PdoWebhookRepository $hooks,
        private readonly DestinationValidator $destinations,
    ) {
    }

    public function handle(Request $request): Response
    {
        $session = $this->auth->sessions->start($request);
        $user = $session->record->userId === null ? null : $this->auth->users->findById($session->record->userId);
        if ($user === null || !$user->isActive()) {
            return $this->auth->sessions->withCookie(Response::redirect('/login'), $session);
        }
        $allowed = $this->auth->authorization->allows($user->id, 'core.admin.access')
            && $this->auth->authorization->allows($user->id, 'core.webhooks.manage');
        $status = $allowed ? 200 : 403;
        $error = null;
        $secret = null;
        $success = null;
        if ($allowed && $request->method() === 'POST') {
            $form = $request->form();
            if (!$this->auth->csrf->validate($session->token, $form['_csrf'] ?? null)) {
                $status = 419;
                $error = 'The form expired. Reload this page and try again.';
            } else {
                try {
                    $action = $form['action'] ?? '';
                    $id = (int) ($form['id'] ?? 0);
                    switch ($action) {
                        case 'create':
                            $created = $this->hooks->create(
                                trim($form['name'] ?? ''),
                                trim($form['url'] ?? ''),
                                $request->formList('events') ?? [],
                                $this->destinations
                            );
                            $secret = $created['secret'];
                            $id = $created['id'];
                            $success = 'Webhook created. Copy the signing secret below; it is shown only once.';
                            break;
                        case 'configure':
                            $this->hooks->configure(
                                $id,
                                $request->formList('events') ?? [],
                                ($form['active'] ?? '') === '1'
                            );
                            $success = 'Webhook settings saved.';
                            break;
                        case 'test':
                            $this->hooks->test($id);
                            $success = 'Test queued. The delivery worker will send it.';
                            break;
                        case 'retry':
                            $this->hooks->retry($form['delivery_id'] ?? '');
                            $success = 'Delivery queued for retry.';
                            break;
                        default:
                            throw new WebhookException('Unknown webhook action.');
                    }
                    $this->auth->audit->record(
                        'webhook.' . $action,
                        $user->id,
                        $request->clientIp(),
                        $request->requestId(),
                        ['webhook_id' => $id]
                    );
                } catch (WebhookException $exception) {
                    $status = 422;
                    $error = $exception->getMessage();
                }
            }
        }
        $csrf = $this->auth->csrf->token($session->token);
        $content = $allowed ? $this->views->render('admin/webhooks', [
            'hooks' => $this->hooks->all(), 'deliveries' => $this->hooks->history(),
            'events' => WebhookEvents::all(), 'csrfToken' => $csrf,
            'secret' => $secret, 'success' => $success, 'error' => $error,
        ]) : $this->views->render('errors/forbidden');
        $response = Response::html($this->views->render('layouts/base', [
            'title' => 'Webhooks', 'theme' => ThemePreference::parse($user->theme), 'content' => $content,
            'authenticatedUser' => $user, 'csrfToken' => $csrf,
            'canAccessAdmin' => $this->auth->authorization->allows($user->id, 'core.admin.access'),
            'canManagePlugins' => $this->auth->authorization->allows($user->id, 'core.plugins.view'),
            'pluginNavigation' => (new PluginNavigation(
                $this->auth->plugins,
                $this->auth->pluginAccess
            ))->forUser($user->id),
        ]), $status)->withHeader('Cache-Control', 'no-store, private');
        return $this->auth->sessions->withCookie($response, $session);
    }
}
