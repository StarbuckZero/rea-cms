<?php

declare(strict_types=1);

namespace ReaCms\Core\Error;

use Psr\Log\LoggerInterface;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Routing\MethodNotAllowed;
use ReaCms\Core\Routing\RouteNotFound;
use ReaCms\Core\View\ViewRenderer;
use Throwable;

final class ErrorHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ViewRenderer $views,
        private readonly bool $debug = false,
    ) {
    }

    public function render(Throwable $exception, Request $request, string $requestId): Response
    {
        if ($exception instanceof RouteNotFound) {
            return $this->publicError($request, 404, 'not_found', 'The requested page could not be found.', $requestId);
        }

        if ($exception instanceof MethodNotAllowed) {
            return $this->publicError(
                $request,
                405,
                'method_not_allowed',
                'The request method is not allowed.',
                $requestId,
            )->withHeader('Allow', implode(', ', $exception->allowedMethods()));
        }

        $this->logger->error('Unhandled request exception.', [
            'requestId' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'exceptionClass' => $exception::class,
            'debug' => $this->debug,
        ]);

        return $this->publicError(
            $request,
            500,
            'internal_error',
            'The request could not be completed.',
            $requestId,
        );
    }

    private function publicError(
        Request $request,
        int $status,
        string $code,
        string $message,
        string $requestId,
    ): Response {
        if ($request->expectsJson()) {
            return Response::json([
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
                'requestId' => $requestId,
            ], $status);
        }

        return Response::html($this->views->render('errors/error', [
            'status' => $status,
            'message' => $message,
            'requestId' => $requestId,
        ]), $status);
    }
}
