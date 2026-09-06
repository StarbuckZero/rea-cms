<?php

declare(strict_types=1);

namespace ReaCms\Setup;

use InvalidArgumentException;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\View\ViewRenderer;
use Throwable;

final class InstallController
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly PlatformInspector $platform,
        private readonly EnvironmentFile $environmentFile,
        private readonly SetupTokenStore $tokens,
        private readonly Installer $installer,
    ) {
    }

    public function form(Request $request): Response
    {
        if ($this->environmentFile->exists()) {
            return Response::redirect('/login');
        }

        return $this->render($request, [], null);
    }

    public function install(Request $request): Response
    {
        if ($this->environmentFile->exists()) {
            return Response::redirect('/login');
        }

        $form = $request->form();
        if (!$this->tokens->consume($form['_csrf'] ?? null)) {
            return $this->render($request, self::safeValues($form), 'Your form expired. Please try again.', 419);
        }

        if (!$this->platform->passes()) {
            return $this->render(
                $request,
                self::safeValues($form),
                'Resolve the required server checks before installing Rea CMS.',
                422,
            );
        }

        try {
            $configuration = InstallConfiguration::fromForm($form);
            $this->installer->install($configuration, $request->requestId(), $request->clientIp());
        } catch (InvalidArgumentException | InstallException $exception) {
            return $this->render($request, self::safeValues($form), $exception->getMessage(), 422);
        } catch (Throwable) {
            return $this->render(
                $request,
                self::safeValues($form),
                'Installation could not be completed. Verify the database credentials and server error log, '
                    . 'then try again.',
                500,
            );
        }

        return Response::redirect('/login?installed=1');
    }

    /**
     * @param array<string, string> $values
     */
    private function render(Request $request, array $values, ?string $error, int $status = 200): Response
    {
        $content = $this->views->render('setup/install', [
            'requirements' => $this->platform->inspect(),
            'csrfToken' => $this->tokens->issue(),
            'values' => $values,
            'error' => $error,
        ]);

        return Response::html($this->views->render('layouts/setup', [
            'title' => 'Install Rea CMS',
            'content' => $content,
        ]), $status)
            ->withHeader('Cache-Control', 'no-store, private')
            ->withHeader('Pragma', 'no-cache');
    }

    /**
     * @param array<string, string> $form
     * @return array<string, string>
     */
    private static function safeValues(array $form): array
    {
        unset(
            $form['_csrf'],
            $form['db_password'],
            $form['admin_password'],
            $form['admin_password_confirmation'],
        );

        return $form;
    }
}
