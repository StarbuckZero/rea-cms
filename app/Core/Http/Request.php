<?php

declare(strict_types=1);

namespace ReaCms\Core\Http;

final class Request
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string|list<string>> $query
     */
    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $headers = [],
        private readonly array $query = [],
        private readonly string $body = '',
        private readonly string $clientIp = '127.0.0.1',
        private readonly ?string $requestId = null,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($name)] = $value;
            }
        }

        if (is_string($_SERVER['CONTENT_TYPE'] ?? null)) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        $query = [];
        foreach ($_GET as $key => $value) {
            if (is_string($key) && (is_string($value) || self::isStringList($value))) {
                $query[$key] = $value;
            }
        }

        $body = file_get_contents('php://input');

        $remoteAddress = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
        $trustedProxyValue = is_string($_ENV['TRUSTED_PROXIES'] ?? null) ? $_ENV['TRUSTED_PROXIES'] : '';
        $trustedProxies = array_values(array_filter(array_map('trim', explode(',', $trustedProxyValue))));
        $clientIp = (new ClientIpResolver())->resolve(
            $remoteAddress,
            $headers['x-forwarded-for'] ?? null,
            $trustedProxies,
        );

        return new self($method, $uri, $headers, $query, $body === false ? '' : $body, $clientIp);
    }

    public function method(): string
    {
        return strtoupper($this->method);
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? rawurldecode($path) : '/';
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function query(): array
    {
        return $this->query;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function form(): array
    {
        $values = [];
        parse_str($this->body, $parsed);

        if ($parsed === [] && str_starts_with(strtolower($this->header('content-type') ?? ''), 'multipart/form-data')) {
            $parsed = $_POST;
        }

        foreach ($parsed as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    public function clientIp(): string
    {
        return $this->clientIp;
    }

    public function userAgent(): string
    {
        return $this->header('user-agent') ?? '';
    }

    public function requestId(): string
    {
        return $this->requestId ?? '';
    }

    public function withRequestId(string $requestId): self
    {
        return new self(
            $this->method,
            $this->uri,
            $this->headers,
            $this->query,
            $this->body,
            $this->clientIp,
            $requestId,
        );
    }

    public function cookie(string $name): ?string
    {
        $cookieHeader = $this->header('cookie');

        if ($cookieHeader === null) {
            return null;
        }

        foreach (explode(';', $cookieHeader) as $cookie) {
            $parts = explode('=', trim($cookie), 2);

            if (count($parts) === 2 && $parts[0] === $name) {
                return rawurldecode($parts[1]);
            }
        }

        return null;
    }

    public function expectsJson(): bool
    {
        return str_starts_with($this->path(), '/api/')
            || str_contains(strtolower($this->header('accept') ?? ''), 'application/json');
    }

    private static function isStringList(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }
}
