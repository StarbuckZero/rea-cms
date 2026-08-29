<?php

declare(strict_types=1);

namespace ReaCms\Core\Http;

final class SecurityHeaders
{
    public function apply(Response $response, string $requestId): Response
    {
        $headers = [
            'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; form-action 'self'; "
                . "frame-ancestors 'none'; img-src 'self' data:; object-src 'none'; "
                . "script-src 'self'; style-src 'self'",
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-Request-ID' => $requestId,
        ];

        foreach ($headers as $name => $value) {
            if ($response->header($name) === null) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }
}
