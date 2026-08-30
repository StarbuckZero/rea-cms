<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use finfo;
use ZipArchive;

final class PackageInspector
{
    private const EXTENSIONS = [
        'json', 'html', 'css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif',
        'woff', 'woff2', 'txt', 'md',
    ];

    public function __construct(
        private readonly ManifestValidator $manifests,
        private readonly int $maximumCompressedBytes = 10_000_000,
        private readonly int $maximumExtractedBytes = 50_000_000,
        private readonly int $maximumFileBytes = 5_000_000,
        private readonly int $maximumFiles = 500,
        private readonly int $maximumDepth = 10,
        private readonly int $maximumRatio = 100,
    ) {
    }

    public function inspect(string $archivePath, string $stagingRoot): StagedPackage
    {
        if (
            !is_file($archivePath) || filesize($archivePath) === false
            || filesize($archivePath) > $this->maximumCompressedBytes
        ) {
            throw new PluginException('The plugin package is missing or exceeds the compressed-size limit.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivePath);
        if (!in_array($mime, ['application/zip', 'application/x-zip-compressed'], true)) {
            throw new PluginException('The plugin package is not a ZIP archive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new PluginException('The plugin ZIP could not be opened.');
        }

        try {
            [$root, $entries] = $this->validateEntries($zip);
            $manifestIndex = $entries[$root . '/plugin.json'] ?? null;
            if (!is_int($manifestIndex)) {
                throw new PluginException('The package must contain plugin.json at its single root.');
            }
            $manifestJson = $zip->getFromIndex($manifestIndex);
            if (!is_string($manifestJson)) {
                throw new PluginException('plugin.json could not be read.');
            }
            $manifest = $this->manifests->validate($manifestJson);
            if ($root !== $manifest->id) {
                throw new PluginException('The package root must exactly match the plugin ID.');
            }

            $stage = rtrim($stagingRoot, '/') . '/' . bin2hex(random_bytes(16));
            if (!mkdir($stage, 0700, true) && !is_dir($stage)) {
                throw new PluginException('A private staging directory could not be created.');
            }
            $this->extractValidated($zip, $entries, $root, $stage);

            $packageHash = hash_file('sha256', $archivePath);
            if ($packageHash === false) {
                throw new PluginException('The package checksum could not be calculated.');
            }

            return new StagedPackage($manifest, $stage . '/' . $root, $packageHash);
        } finally {
            $zip->close();
        }
    }

    /** @return array{string, array<string, int>} */
    private function validateEntries(ZipArchive $zip): array
    {
        if ($zip->numFiles < 1 || $zip->numFiles > $this->maximumFiles) {
            throw new PluginException('The package file-count limit was exceeded.');
        }
        $entries = [];
        $roots = [];
        $total = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if (!is_array($stat) || !is_string($stat['name'] ?? null)) {
                throw new PluginException('A ZIP entry could not be inspected.');
            }
            $name = str_replace('\\', '/', $stat['name']);
            $this->validatePath($name);
            $normalized = rtrim($name, '/');
            if (isset($entries[$normalized])) {
                throw new PluginException('The package contains duplicate normalized paths.');
            }
            $roots[explode('/', $normalized)[0]] = true;
            $depth = substr_count($normalized, '/');
            if ($depth > $this->maximumDepth) {
                throw new PluginException('The package nesting-depth limit was exceeded.');
            }
            $size = (int) ($stat['size'] ?? 0);
            $compressed = (int) ($stat['comp_size'] ?? 0);
            $total += $size;
            if (
                $size > $this->maximumFileBytes || $total > $this->maximumExtractedBytes
                || ($compressed === 0 && $size > 0)
                || ($compressed > 0 && $size / $compressed > $this->maximumRatio)
            ) {
                throw new PluginException('The package exceeds extraction safety limits.');
            }
            if (!$this->isDirectory($name)) {
                $this->validateFile($zip, $index, $normalized);
                $entries[$normalized] = $index;
            }
        }
        if (count($roots) !== 1) {
            throw new PluginException('The package must contain exactly one root directory.');
        }
        return [(string) array_key_first($roots), $entries];
    }

    private function validatePath(string $name): void
    {
        if (
            $name === '' || str_contains($name, "\0") || preg_match('/[\x00-\x1f\x7f]/', $name) === 1
            || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) === 1
        ) {
            throw new PluginException('The package contains an unsafe path.');
        }
        foreach (explode('/', $name) as $segment) {
            if ($segment === '') {
                continue;
            }
            if ($segment === '..' || $segment === '.' || str_starts_with($segment, '.')) {
                throw new PluginException('The package contains traversal or hidden paths.');
            }
        }
    }

    private function validateFile(ZipArchive $zip, int $index, string $name): void
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw new PluginException('The package contains a forbidden file type.');
        }
        $attributes = 0;
        $operations = 0;
        if (
            $zip->getExternalAttributesIndex($index, $operations, $attributes)
            && (($attributes >> 16) & 0170000) === 0120000
        ) {
            throw new PluginException('Symbolic links are forbidden in plugin packages.');
        }
        $prefix = $zip->getFromIndex($index, 256);
        if (
            !is_string($prefix) || str_contains(strtolower($prefix), '<?php')
            || str_starts_with($prefix, "#!")
        ) {
            throw new PluginException('The package contains executable or unreadable content.');
        }
    }

    /** @param array<string, int> $entries */
    private function extractValidated(ZipArchive $zip, array $entries, string $root, string $stage): void
    {
        foreach ($entries as $name => $index) {
            $relative = substr($name, strlen($root) + 1);
            $destination = $stage . '/' . $root . '/' . $relative;
            $directory = dirname($destination);
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new PluginException('A staging directory could not be created.');
            }
            $contents = $zip->getFromIndex($index);
            if (!is_string($contents) || file_put_contents($destination, $contents, LOCK_EX) === false) {
                throw new PluginException('A validated package file could not be staged.');
            }
            chmod($destination, 0600);
        }
    }

    private function isDirectory(string $name): bool
    {
        return str_ends_with($name, '/');
    }
}
