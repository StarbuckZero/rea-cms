<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use JsonException;

final class PendingPackageStore
{
    public function __construct(
        private readonly string $stagingRoot,
        private readonly ManifestValidator $manifests,
    ) {
    }

    public function put(StagedPackage $package, string $sessionHash): string
    {
        $manifestJson = file_get_contents($package->directory . '/plugin.json');
        if (!is_string($manifestJson) || !hash_equals($package->manifest->hash, hash('sha256', $manifestJson))) {
            throw new PluginException('The staged plugin manifest no longer matches its validated contents.');
        }
        $token = bin2hex(random_bytes(24));
        $record = [
            'sessionHash' => $sessionHash,
            'directory' => $package->directory,
            'packageHash' => $package->packageHash,
            'manifestJson' => $manifestJson,
            'createdAt' => time(),
        ];
        try {
            $json = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new PluginException('The pending plugin could not be recorded.', previous: $exception);
        }
        if (file_put_contents($this->recordPath($token), $json, LOCK_EX) === false) {
            throw new PluginException('The pending plugin could not be recorded.');
        }
        chmod($this->recordPath($token), 0600);
        return $token;
    }

    public function take(string $token, string $sessionHash): StagedPackage
    {
        if (preg_match('/^[a-f0-9]{48}$/D', $token) !== 1) {
            throw new PluginException('The pending plugin confirmation is invalid.');
        }
        $path = $this->recordPath($token);
        $processing = $path . '.processing';
        if (!is_file($path) || !rename($path, $processing)) {
            throw new PluginException('The pending plugin has expired or was already processed.');
        }
        try {
            $json = file_get_contents($processing);
            $data = is_string($json) ? json_decode($json, true, 64, JSON_THROW_ON_ERROR) : null;
            if (
                !is_array($data) || !is_string($data['sessionHash'] ?? null)
                || !hash_equals($data['sessionHash'], $sessionHash)
                || !is_int($data['createdAt'] ?? null) || $data['createdAt'] < time() - 1800
                || !is_string($data['directory'] ?? null) || !is_string($data['packageHash'] ?? null)
                || !is_string($data['manifestJson'] ?? null)
            ) {
                throw new PluginException('The pending plugin confirmation is invalid or expired.');
            }
            $root = realpath($this->stagingRoot);
            $directory = realpath($data['directory']);
            if (!is_string($root) || !is_string($directory) || !str_starts_with($directory . '/', $root . '/')) {
                throw new PluginException('The pending plugin directory is no longer safe.');
            }
            return new StagedPackage(
                $this->manifests->validate($data['manifestJson']),
                $directory,
                $data['packageHash'],
            );
        } catch (JsonException $exception) {
            throw new PluginException('The pending plugin record is invalid.', previous: $exception);
        } finally {
            if (is_file($processing)) {
                unlink($processing);
            }
        }
    }

    private function recordPath(string $token): string
    {
        if (!is_dir($this->stagingRoot) && !mkdir($this->stagingRoot, 0700, true) && !is_dir($this->stagingRoot)) {
            throw new PluginException('Private plugin staging could not be created.');
        }
        return rtrim($this->stagingRoot, '/') . '/pending-' . $token . '.json';
    }
}
