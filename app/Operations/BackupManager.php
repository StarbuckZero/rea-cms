<?php

declare(strict_types=1);

namespace ReaCms\Operations;

use JsonException;

final class BackupManager
{
    public function __construct(private readonly BackupDatabase $database, private readonly string $backupRoot)
    {
    }

    /**
     * @param list<string> $tables
     * @param array<string, string> $plugins
     * @param list<array<string, mixed>> $media
     */
    public function create(string $scope, array $tables, array $plugins, array $media): string
    {
        if (!in_array($scope, ['full', 'core', 'plugin'], true)) {
            throw new BackupException('The backup scope is invalid.');
        }
        $payload = [
            'formatVersion' => 1,
            'scope' => $scope,
            'createdAt' => gmdate(DATE_ATOM),
            'tables' => $this->database->export($tables),
            'plugins' => $plugins,
            'media' => $media,
        ];
        try {
            $canonical = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $document = json_encode([
                'checksum' => hash('sha256', $canonical),
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (JsonException $exception) {
            throw new BackupException('Backup serialization failed.', previous: $exception);
        }
        if (!is_dir($this->backupRoot) && !mkdir($this->backupRoot, 0700, true) && !is_dir($this->backupRoot)) {
            throw new BackupException('Private backup storage could not be created.');
        }
        $path = rtrim($this->backupRoot, '/') . '/backup-' . bin2hex(random_bytes(12)) . '.json';
        if (file_put_contents($path, $document, LOCK_EX) === false || !chmod($path, 0600)) {
            throw new BackupException('The backup could not be stored privately.');
        }
        return $path;
    }

    public function restoreIntoEmpty(string $path): void
    {
        if (!$this->database->isEmpty()) {
            throw new BackupException('Restore requires an empty target database.');
        }
        $json = file_get_contents($path);
        try {
            $document = is_string($json) ? json_decode($json, true, 64, JSON_THROW_ON_ERROR) : null;
            $payload = is_array($document) ? ($document['payload'] ?? null) : null;
            $checksum = is_array($document) ? ($document['checksum'] ?? null) : null;
            $canonical = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new BackupException('The backup document is invalid.', previous: $exception);
        }
        if (
            !is_array($payload) || !is_string($checksum) || !hash_equals($checksum, hash('sha256', $canonical))
            || ($payload['formatVersion'] ?? null) !== 1 || !is_array($payload['tables'] ?? null)
        ) {
            throw new BackupException('The backup integrity check failed.');
        }
        $this->database->restore($payload['tables']);
    }
}
