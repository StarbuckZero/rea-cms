<?php

declare(strict_types=1);

namespace ReaCms\Setup;

use RuntimeException;

final class SetupTokenStore
{
    private const MAXIMUM_AGE = 1800;

    private readonly string $path;

    public function __construct(string $projectRoot)
    {
        $this->path = rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/storage/install-token.json';
    }

    public function issue(): string
    {
        $token = bin2hex(random_bytes(32));
        $payload = json_encode([
            'hash' => hash('sha256', $token),
            'issued_at' => time(),
        ], JSON_THROW_ON_ERROR);

        if (file_put_contents($this->path, $payload, LOCK_EX) !== strlen($payload)) {
            throw new RuntimeException('The installer could not create its security token.');
        }
        chmod($this->path, 0600);

        return $token;
    }

    public function consume(?string $provided): bool
    {
        if (!is_string($provided) || preg_match('/^[a-f0-9]{64}$/', $provided) !== 1 || !is_file($this->path)) {
            return false;
        }

        $contents = file_get_contents($this->path);
        @unlink($this->path);
        if ($contents === false) {
            return false;
        }

        $payload = json_decode($contents, true);
        $hash = is_array($payload) ? ($payload['hash'] ?? null) : null;
        $issuedAt = is_array($payload) ? ($payload['issued_at'] ?? null) : null;

        return is_string($hash)
            && is_int($issuedAt)
            && $issuedAt >= time() - self::MAXIMUM_AGE
            && $issuedAt <= time() + 60
            && hash_equals($hash, hash('sha256', $provided));
    }
}
