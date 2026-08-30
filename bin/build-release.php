#!/usr/bin/env php
<?php

declare(strict_types=1);

use ReaCms\Release\ArtifactIntegrity;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$version = $argv[1] ?? '';

if (preg_match('/^\d+\.\d+\.\d+(?:-(?:alpha|beta|rc)\.\d+)?$/', $version) !== 1) {
    fwrite(STDERR, "Usage: php bin/build-release.php <semver>\n");
    exit(1);
}

$dist = $root . '/dist';
$staging = $dist . '/rea-cms-' . $version;
$archive = $staging . '.zip';

if (file_exists($staging) || file_exists($archive)) {
    fwrite(STDERR, "Release output already exists; remove it explicitly before rebuilding.\n");
    exit(1);
}

if (!is_dir($dist) && !mkdir($dist, 0700, true)) {
    throw new RuntimeException('Could not create the release output directory.');
}

$include = [
    '.env.example',
    'README.md',
    'app',
    'bin',
    'composer.json',
    'composer.lock',
    'config',
    'database',
    'docs',
    'plugins',
    'public',
    'storage',
];

/** @param callable(string, string): void $copy */
$copy = function (string $source, string $target) use (&$copy): void {
    if (is_link($source)) {
        throw new RuntimeException('Symbolic links are not permitted in release inputs.');
    }

    $normalizedTarget = str_replace('\\', '/', $target);
    if (
        is_file($source)
        && preg_match('#/storage/(?:backups|cache|logs|plugins/staging|sessions|uploads)/#', $normalizedTarget) === 1
        && basename($source) !== '.gitkeep'
    ) {
        return;
    }

    if (is_dir($source)) {
        if (!is_dir($target) && !mkdir($target, 0755, true)) {
            throw new RuntimeException('Could not create a release directory.');
        }

        $entries = scandir($source);
        if ($entries === false) {
            throw new RuntimeException('Could not read a release source directory.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $copy($source . '/' . $entry, $target . '/' . $entry);
        }

        return;
    }

    if (!copy($source, $target)) {
        throw new RuntimeException('Could not copy a release file.');
    }
};

mkdir($staging, 0755, true);
foreach ($include as $path) {
    $copy($root . '/' . $path, $staging . '/' . $path);
}

$composerCommand = sprintf(
    'composer install --working-dir=%s --no-dev --classmap-authoritative --no-interaction --prefer-dist',
    escapeshellarg($staging),
);
passthru($composerCommand, $composerStatus);
if ($composerStatus !== 0) {
    throw new RuntimeException('Production dependency installation failed.');
}

$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('Could not create the release archive.');
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY,
);

foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile()) {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($staging) + 1);
    $zip->addFile($file->getPathname(), $relative);
}
$zip->close();

ArtifactIntegrity::write($archive, $archive . '.sha256');
printf("Built %s\n", $archive);
