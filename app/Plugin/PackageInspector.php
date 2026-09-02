<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use finfo;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use ZipArchive;

final class PackageInspector
{
    private const EXTENSIONS = [
        'json', 'html', 'css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif',
        'woff', 'woff2', 'txt', 'md',
    ];

    public function __construct(
        private readonly ManifestValidator $manifests,
        private readonly DeclarativeMigration $migrations = new DeclarativeMigration(),
        private readonly SafeTemplate $templates = new SafeTemplate(),
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

        $stage = null;
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
            $this->validateDeclarativeFiles($manifest, $stage . '/' . $root);

            $packageHash = hash_file('sha256', $archivePath);
            if ($packageHash === false) {
                throw new PluginException('The package checksum could not be calculated.');
            }

            return new StagedPackage($manifest, $stage . '/' . $root, $packageHash);
        } catch (Throwable $exception) {
            if (is_string($stage) && is_dir($stage)) {
                $this->removeDirectory($stage);
            }
            throw $exception;
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
        $seen = [];
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
            if (isset($seen[$normalized])) {
                throw new PluginException('The package contains duplicate normalized paths.');
            }
            $seen[$normalized] = true;
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
        if ($zip->getExternalAttributesIndex($index, $operations, $attributes)) {
            $type = ($attributes >> 16) & 0170000;
            if ($type !== 0 && $type !== 0100000) {
                throw new PluginException('Links and special files are forbidden in plugin packages.');
            }
        }
        $contents = $zip->getFromIndex($index);
        if (
            !is_string($contents) || str_contains(strtolower($contents), '<?php')
            || str_starts_with($contents, "#!")
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

    private function validateDeclarativeFiles(Manifest $manifest, string $directory): void
    {
        $migrationFiles = glob($directory . '/migrations/*.json') ?: [];
        sort($migrationFiles, SORT_STRING);
        foreach ($migrationFiles as $file) {
            if (preg_match('/^[0-9]{3}_[a-z0-9_]+\.json$/D', basename($file)) !== 1) {
                throw new PluginException('Plugin migration filenames must be ordered and normalized.');
            }
            $json = file_get_contents($file);
            if (!is_string($json)) {
                throw new PluginException('A plugin migration could not be read.');
            }
            $this->migrations->compile($manifest->id, $manifest, $json);
        }

        $templateDirectory = $directory . '/templates';
        $formats = is_array($manifest->document['api']['formats'] ?? null)
            ? $manifest->document['api']['formats']
            : [];
        foreach (['html', 'txt'] as $format) {
            if (!in_array($format, $formats, true)) {
                continue;
            }
            foreach (['list', 'detail'] as $mode) {
                if (!is_file($templateDirectory . '/api/' . $mode . '.' . $format)) {
                    throw new PluginException(sprintf(
                        'Plugins exposing %s APIs must define templates/api/%s.%s.',
                        $format,
                        $mode,
                        $format,
                    ));
                }
            }
        }
        if (!is_dir($templateDirectory)) {
            return;
        }
        $templateFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $templateDirectory,
            RecursiveDirectoryIterator::SKIP_DOTS,
        ));
        foreach ($templateFiles as $file) {
            if (
                !$file instanceof SplFileInfo || !$file->isFile()
                || !in_array(strtolower($file->getExtension()), ['html', 'txt'], true)
            ) {
                continue;
            }
            $template = file_get_contents($file->getPathname());
            if (!is_string($template)) {
                throw new PluginException('A plugin template could not be read.');
            }
            strtolower($file->getExtension()) === 'txt'
                ? $this->templates->renderText($template, [])
                : $this->templates->render($template, []);
        }
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
