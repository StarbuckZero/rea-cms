<?php

declare(strict_types=1);

namespace ReaCms\Setup;

use RuntimeException;

final class EnvironmentFile
{
    private readonly string $path;

    public function __construct(string $projectRoot)
    {
        $this->path = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function assertCanCreate(): void
    {
        if ($this->exists()) {
            throw new RuntimeException('Rea CMS is already configured.');
        }

        $directory = dirname($this->path);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('The application directory is not writable.');
        }

        $probe = tempnam($directory, '.rea-install-');
        if ($probe === false) {
            throw new RuntimeException('The installer cannot create files in the application directory.');
        }
        chmod($probe, 0600);
        unlink($probe);
    }

    /**
     * @param array<string, string> $values
     */
    public function create(array $values): void
    {
        $this->assertCanCreate();
        $contents = '';

        foreach ($values as $key => $value) {
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1 || str_contains($value, "\0")) {
                throw new RuntimeException('The environment configuration contains an invalid value.');
            }
            $contents .= $key . '=' . self::quote($value) . PHP_EOL;
        }

        $temporary = $this->path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) {
            @unlink($temporary);
            throw new RuntimeException('The environment file could not be written.');
        }
        chmod($temporary, 0600);

        if ($this->exists() || !rename($temporary, $this->path)) {
            @unlink($temporary);
            throw new RuntimeException('The environment file could not be installed.');
        }
        chmod($this->path, 0600);
    }

    private static function quote(string $value): string
    {
        return '"' . str_replace(
            ['\\', '"', '$', "\r", "\n"],
            ['\\\\', '\\"', '\\$', '\\r', '\\n'],
            $value,
        ) . '"';
    }
}
