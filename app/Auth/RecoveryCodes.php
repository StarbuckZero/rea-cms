<?php

declare(strict_types=1);

namespace ReaCms\Auth;

final class RecoveryCodes
{
    /**
     * @return array{plain: list<string>, hashes: list<string>}
     */
    public function generate(int $count = 8): array
    {
        $plain = [];
        $hashes = [];

        for ($index = 0; $index < $count; $index++) {
            $code = strtoupper(bin2hex(random_bytes(5)));
            $plain[] = substr($code, 0, 5) . '-' . substr($code, 5);
            $hashes[] = password_hash($plain[$index], PASSWORD_DEFAULT);
        }

        return ['plain' => $plain, 'hashes' => $hashes];
    }

    public function verify(string $code, string $hash): bool
    {
        return password_verify(strtoupper(trim($code)), $hash);
    }
}
