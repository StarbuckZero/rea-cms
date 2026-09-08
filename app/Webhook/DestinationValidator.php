<?php

declare(strict_types=1);

namespace ReaCms\Webhook;

use ReaCms\Api\Policy\NetworkMatcher;

final class DestinationValidator
{
    /** @var callable(string): list<string> */
    private $resolve;

    /** @param callable(string): list<string> $resolve */
    public function __construct(callable $resolve)
    {
        $this->resolve = $resolve;
    }

    /** @return list<string> */
    public function validate(string $url): array
    {
        $parts = parse_url($url);
        if (
            !is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !is_string($parts['host'] ?? null) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || preg_match('/[\x00-\x20\x7f]/', $url) === 1
            || (isset($parts['port']) && $parts['port'] !== 443)
        ) {
            throw new WebhookException('Webhook destinations must use credential-free HTTPS on port 443.');
        }
        $addresses = ($this->resolve)($parts['host']);
        if ($addresses === []) {
            throw new WebhookException('The webhook destination did not resolve.');
        }
        $networks = new NetworkMatcher();
        foreach ($addresses as $address) {
            $publicAddress = filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_GLOBAL_RANGE,
            );
            if (
                $publicAddress === false
                || $networks->contains('224.0.0.0/4', $address)
                || (str_contains($address, ':')
                    && (!$networks->contains('2000::/3', $address)
                        || $networks->contains('2001::/23', $address)
                        || $networks->contains('2002::/16', $address)))
            ) {
                throw new WebhookException('The webhook destination resolves to a disallowed network.');
            }
        }
        return array_values(array_unique($addresses));
    }

    /** @param list<string> $approved */
    public function validateRebinding(string $url, array $approved): void
    {
        $current = $this->validate($url);
        sort($current, SORT_STRING);
        sort($approved, SORT_STRING);
        if ($current !== $approved) {
            throw new WebhookException('Webhook DNS changed before delivery.');
        }
    }
}
