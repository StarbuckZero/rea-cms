<?php

declare(strict_types=1);

namespace ReaCms\Setup;

use RuntimeException;

final class InstallState
{
    private readonly string $pendingPath;
    private readonly string $completedPath;

    public function __construct(string $projectRoot)
    {
        $storage = rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/storage';
        $this->pendingPath = $storage . '/install-pending.json';
        $this->completedPath = $storage . '/install-completed.json';
    }

    public function matchesPending(string $fingerprint): bool
    {
        if (!is_file($this->pendingPath)) {
            return false;
        }
        $contents = file_get_contents($this->pendingPath);
        $payload = $contents === false ? null : json_decode($contents, true);

        return is_array($payload)
            && is_string($payload['fingerprint'] ?? null)
            && hash_equals($payload['fingerprint'], $fingerprint);
    }

    public function begin(string $fingerprint): void
    {
        $this->write($this->pendingPath, [
            'fingerprint' => $fingerprint,
            'started_at' => gmdate(DATE_ATOM),
        ]);
    }

    public function complete(string $version): void
    {
        $this->write($this->completedPath, [
            'version' => $version,
            'completed_at' => gmdate(DATE_ATOM),
        ]);
        @unlink($this->pendingPath);
    }

    /**
     * @param array<string, string> $payload
     */
    private function write(string $path, array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
        if (file_put_contents($path, $json, LOCK_EX) !== strlen($json)) {
            throw new RuntimeException('The installation state could not be saved.');
        }
        chmod($path, 0600);
    }
}
