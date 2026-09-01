<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

final class PodcastUrlGuard
{
    /** @return array{url: string, host: string, port: int, address: string} */
    public function validate(string $url): array
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new PodcastException('The podcast feed URL is invalid.');
        }
        $scheme = strtolower(is_string($parts['scheme'] ?? null) ? $parts['scheme'] : '');
        $host = strtolower(is_string($parts['host'] ?? null) ? $parts['host'] : '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user'])) {
            throw new PodcastException('Podcast feeds must use an HTTP or HTTPS URL without credentials.');
        }
        $port = is_int($parts['port'] ?? null) ? $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if (($scheme === 'https' && $port !== 443) || ($scheme === 'http' && $port !== 80)) {
            throw new PodcastException('Podcast feed URLs may only use the standard HTTP or HTTPS port.');
        }
        $address = filter_var($host, FILTER_VALIDATE_IP) !== false ? $host : $this->resolve($host);
        if (!$this->isPublicAddress($address)) {
            throw new PodcastException('The podcast feed host does not resolve to a public address.');
        }
        return ['url' => $url, 'host' => $host, 'port' => $port, 'address' => $address];
    }

    private function resolve(string $host): string
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false || $records === []) {
            throw new PodcastException('The podcast feed host could not be resolved.');
        }
        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address) && $this->isPublicAddress($address)) {
                return $address;
            }
        }
        throw new PodcastException('The podcast feed host does not resolve to a public address.');
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
