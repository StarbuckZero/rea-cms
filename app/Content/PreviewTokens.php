<?php

declare(strict_types=1);

namespace ReaCms\Content;

final class PreviewTokens
{
    /** @return array{plaintext: string, hash: string} */
    public function generate(): array
    {
        $plaintext = bin2hex(random_bytes(32));
        return ['plaintext' => $plaintext, 'hash' => hash('sha256', $plaintext)];
    }

    public function matches(string $plaintext, string $storedHash): bool
    {
        return preg_match('/^[a-f0-9]{64}$/D', $plaintext) === 1
            && hash_equals($storedHash, hash('sha256', $plaintext));
    }
}
