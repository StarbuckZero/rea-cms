<?php

declare(strict_types=1);

namespace ReaCms\Core\Http;

use ReaCms\Core\Error\ErrorHandler;
use ReaCms\Core\Routing\Router;
use Throwable;

final class Application
{
    /** @var callable(): string */
    private $requestIdFactory;

    /**
     * @param callable(): string|null $requestIdFactory
     */
    public function __construct(
        private readonly Router $router,
        private readonly ErrorHandler $errors,
        private readonly SecurityHeaders $securityHeaders,
        ?callable $requestIdFactory = null,
    ) {
        $this->requestIdFactory = $requestIdFactory ?? RequestId::generate(...);
    }

    public function handle(Request $request): Response
    {
        $requestId = ($this->requestIdFactory)();

        try {
            $response = $this->router->dispatch($request);
        } catch (Throwable $exception) {
            $response = $this->errors->render($exception, $request, $requestId);
        }

        return $this->securityHeaders->apply($response, $requestId);
    }
}
