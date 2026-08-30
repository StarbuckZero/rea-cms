<?php

declare(strict_types=1);

namespace ReaCms\Api\Policy;

final class OriginAllowlist
{
    /** @var list<string> */
    private array $origins;

    /** @param list<string> $origins */
    public function __construct(array $origins)
    {
        $this->origins = array_values(array_filter(array_map(self::normalize(...), $origins)));
    }

    public function allows(?string $origin): bool
    {
        $normalized = self::normalize($origin);

        return $normalized !== null && in_array($normalized, $this->origins, true);
    }

    private static function normalize(?string $origin): ?string
    {
        if ($origin === null || trim($origin) === '' || strtolower(trim($origin)) === 'null') {
            return null;
        }

        $parts = parse_url(trim($origin));
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . '://' . $host . $port;
    }
}
