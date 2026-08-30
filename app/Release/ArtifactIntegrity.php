<?php

declare(strict_types=1);

namespace ReaCms\Release;

use RuntimeException;

final class ArtifactIntegrity
{
    public static function checksum(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Release artifact is not readable.');
        }

        $checksum = hash_file('sha256', $path);
        if ($checksum === false) {
            throw new RuntimeException('Release artifact checksum failed.');
        }

        return $checksum;
    }

    public static function write(string $artifactPath, string $checksumPath): void
    {
        $line = sprintf("%s  %s\n", self::checksum($artifactPath), basename($artifactPath));
        if (file_put_contents($checksumPath, $line, LOCK_EX) === false) {
            throw new RuntimeException('Release checksum file could not be written.');
        }
    }

    public static function verify(string $artifactPath, string $checksumPath): bool
    {
        $contents = is_file($checksumPath) ? file_get_contents($checksumPath) : false;
        if ($contents === false) {
            return false;
        }

        $parts = preg_split('/\s+/', trim($contents));
        if (!is_array($parts) || count($parts) !== 2) {
            return false;
        }

        [$expected, $filename] = $parts;

        return $filename === basename($artifactPath)
            && preg_match('/^[a-f0-9]{64}$/', $expected) === 1
            && hash_equals($expected, self::checksum($artifactPath));
    }
}
