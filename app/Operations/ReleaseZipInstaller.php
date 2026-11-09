<?php

declare(strict_types=1);

namespace ReaCms\Operations;

use RuntimeException;
use ZipArchive;

final class ReleaseZipInstaller
{
    public function __construct(private readonly string $root)
    {
    }

    public function install(string $archive, string $checksum): string
    {
        if (
            preg_match('/^[a-f0-9]{64}$/iD', $checksum) !== 1
            || !hash_equals(strtolower($checksum), hash_file('sha256', $archive) ?: '')
        ) {
            throw new RuntimeException('The ZIP does not match the release SHA-256 checksum.');
        }
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('The release ZIP could not be opened.');
        }
        $work = $this->root . '/storage/backups/upgrade-' . bin2hex(random_bytes(8));
        $files = [];
        $bytes = 0;
        try {
            if ($zip->numFiles > 20000) {
                throw new RuntimeException('The release contains too many files.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    throw new RuntimeException('The release ZIP directory is unreadable.');
                }
                $name = $stat['name'];
                $path = rtrim($name, '/');
                $parts = explode('/', $path);
                $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                if (
                    $path === '' || str_contains($name, '\\') || str_contains($name, ':')
                    || preg_match('/[\x00-\x1f\x7f]/', $name) === 1
                    || array_intersect($parts, ['', '.', '..']) !== []
                    || (($attributes >> 16) & 0170000) === 0120000
                ) {
                    throw new RuntimeException('The release contains an unsafe path.');
                }
                $bytes += $stat['size'];
                if ($bytes > 256 * 1024 * 1024) {
                    throw new RuntimeException('The expanded release exceeds 256 MB.');
                }
                // Runtime data is never installed from a release archive.
                if ($parts[0] === 'storage') {
                    continue;
                }
                if (
                    !in_array($parts[0], [
                    'app', 'bin', 'config', 'database', 'docs', 'plugins', 'public', 'resources', 'vendor',
                    'VERSION', '.env.example', 'README.md', 'LICENSE', 'composer.json', 'composer.lock',
                    ], true)
                ) {
                    throw new RuntimeException('Use a built Rea CMS release ZIP, not a source or backup ZIP.');
                }
                $target = $this->root;
                foreach ($parts as $part) {
                    $target .= '/' . $part;
                    if (is_link($target)) {
                        throw new RuntimeException('Release destinations must not contain symbolic links.');
                    }
                }
                if (str_ends_with($name, '/')) {
                    continue;
                }
                if (isset($files[$path])) {
                    throw new RuntimeException('The release contains duplicate paths.');
                }
                $files[$path] = true;
            }
            foreach (
                ['VERSION', 'app/Core/Http/ApplicationFactory.php', 'public/index.php',
                'vendor/autoload.php', 'composer.json', 'config/bootstrap.php'] as $required
            ) {
                if (!isset($files[$required])) {
                    throw new RuntimeException(
                        'The ZIP is missing required application or production dependency files.',
                    );
                }
            }
            $version = trim($zip->getFromName('VERSION') ?: '');
            if (preg_match('/^\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)\.\d+)?$/D', $version) !== 1) {
                throw new RuntimeException('The release version is invalid.');
            }
            $current = trim(file_get_contents($this->root . '/VERSION') ?: '');
            if (version_compare($version, $current, '<')) {
                throw new RuntimeException('Installing an older release is not supported.');
            }
            if (disk_free_space($this->root . '/storage') < $bytes * 3) {
                throw new RuntimeException('There is not enough disk space to stage and back up this release.');
            }
            foreach (array_keys($files) as $path) {
                $staged = $work . '/new/' . $path;
                $this->directory(dirname($staged));
                $input = $zip->getStream($path);
                $output = fopen($staged, 'wb');
                if ($input === false || $output === false) {
                    throw new RuntimeException('A release file could not be staged.');
                }
                $copied = stream_copy_to_stream($input, $output);
                fclose($input);
                fclose($output);
                $stat = $zip->statName($path);
                if ($copied === false || $stat === false || $copied !== $stat['size']) {
                    throw new RuntimeException('A release file was not completely extracted.');
                }
                $target = $this->root . '/' . $path;
                $this->directory(dirname($target));
                if (!is_writable(dirname($target)) || (file_exists($target) && !is_file($target))) {
                    throw new RuntimeException('Application files must be writable by PHP to install this ZIP.');
                }
                if (is_file($target)) {
                    $backup = $work . '/old/' . $path;
                    $this->directory(dirname($backup));
                    if (!copy($target, $backup)) {
                        throw new RuntimeException('An existing application file could not be backed up.');
                    }
                }
            }
        } finally {
            $zip->close();
        }
        $marker = $this->root . '/storage/upgrade-maintenance';
        if (file_put_contents($marker, $work . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Could not enable maintenance mode.');
        }
        // Keep the journal and backups for recovery if PHP is interrupted mid-install.
        if (file_put_contents($work . '/files.json', json_encode(array_keys($files), JSON_THROW_ON_ERROR)) === false) {
            unlink($marker);
            throw new RuntimeException('Could not save the upgrade recovery journal.');
        }
        $installed = [];
        try {
            foreach (array_keys($files) as $path) {
                if (!rename($work . '/new/' . $path, $this->root . '/' . $path)) {
                    throw new RuntimeException('Could not replace an application file.');
                }
                $installed[] = $path;
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($this->root . '/' . $path, true);
                }
            }
        } catch (\Throwable $error) {
            foreach (array_reverse($installed) as $path) {
                $backup = $work . '/old/' . $path;
                $target = $this->root . '/' . $path;
                $restored = is_file($backup) ? copy($backup, $target) : unlink($target);
                if (!$restored) {
                    throw new RuntimeException(
                        'Restore the application backup in cPanel before removing maintenance mode.',
                    );
                }
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($target, true);
                }
            }
            unlink($marker);
            throw $error;
        }
        unlink($marker);
        return $version;
    }

    private function directory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            throw new RuntimeException('Could not create an upgrade directory.');
        }
    }
}
