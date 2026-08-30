<?php

declare(strict_types=1);

namespace ReaCms\Core\Http;

use ReaCms\Api\Policy\NetworkMatcher;

final class ClientIpResolver
{
    public function __construct(private readonly NetworkMatcher $networks = new NetworkMatcher())
    {
    }

    /** @param list<string> $trustedProxies */
    public function resolve(string $remoteAddress, ?string $forwardedFor, array $trustedProxies): string
    {
        if (filter_var($remoteAddress, FILTER_VALIDATE_IP) === false) {
            return '127.0.0.1';
        }

        if ($forwardedFor === null || !$this->networks->matchesAny($trustedProxies, $remoteAddress)) {
            return $remoteAddress;
        }

        $addresses = array_map('trim', explode(',', $forwardedFor));
        $candidate = $addresses[0] ?? '';

        return filter_var($candidate, FILTER_VALIDATE_IP) !== false ? $candidate : $remoteAddress;
    }
}
