<?php

declare(strict_types=1);

namespace ReaCms\Auth;

use RuntimeException;

final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function verify(string $secret, string $code, int $timestamp, int $window = 1): bool
    {
        if (preg_match('/^[0-9]{6}$/', $code) !== 1 || $window < 0 || $window > 10) {
            return false;
        }

        $counter = intdiv($timestamp, 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function code(string $secret, int $counter): string
    {
        if ($counter < 0) {
            throw new RuntimeException('The TOTP counter cannot be negative.');
        }

        $key = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', pack('J', $counter), $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        );

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0');
            $encoded .= self::ALPHABET[intval($chunk, 2)];
        }

        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $normalized = strtoupper(rtrim($encoded, '='));

        if ($normalized === '' || preg_match('/^[A-Z2-7]+$/', $normalized) !== 1) {
            throw new RuntimeException('The TOTP secret is invalid.');
        }

        $bits = '';
        foreach (str_split($normalized) as $character) {
            $position = strpos(self::ALPHABET, $character);
            if ($position === false) {
                throw new RuntimeException('The TOTP secret is invalid.');
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(intval($chunk, 2));
            }
        }

        return $decoded;
    }
}
