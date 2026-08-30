<?php

declare(strict_types=1);

namespace ReaCms\Api\Policy;

final class NetworkMatcher
{
    public function contains(string $cidr, string $address): bool
    {
        [$network, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($network === null) {
            return false;
        }
        $networkBytes = @inet_pton($network);
        $addressBytes = @inet_pton($address);

        if ($networkBytes === false || $addressBytes === false || strlen($networkBytes) !== strlen($addressBytes)) {
            return false;
        }

        $bits = strlen($networkBytes) * 8;
        $prefixLength = $prefix === null ? $bits : filter_var($prefix, FILTER_VALIDATE_INT);

        if (!is_int($prefixLength) || $prefixLength < 0 || $prefixLength > $bits) {
            return false;
        }

        $wholeBytes = intdiv($prefixLength, 8);
        if (substr($networkBytes, 0, $wholeBytes) !== substr($addressBytes, 0, $wholeBytes)) {
            return false;
        }

        $remainingBits = $prefixLength % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($networkBytes[$wholeBytes]) & $mask) === (ord($addressBytes[$wholeBytes]) & $mask);
    }

    /** @param list<string> $networks */
    public function matchesAny(array $networks, string $address): bool
    {
        foreach ($networks as $network) {
            if ($this->contains(trim($network), $address)) {
                return true;
            }
        }

        return false;
    }
}
