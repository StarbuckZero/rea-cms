#!/usr/bin/env php
<?php

declare(strict_types=1);

use ReaCms\Release\ArtifactIntegrity;

require dirname(__DIR__) . '/vendor/autoload.php';

$archive = $argv[1] ?? '';
$checksum = $archive . '.sha256';

if ($archive === '' || !ArtifactIntegrity::verify($archive, $checksum)) {
    fwrite(STDERR, "Release checksum verification failed.\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($archive) !== true) {
    fwrite(STDERR, "Release archive could not be opened.\n");
    exit(1);
}

$forbidden = [
    '.git/',
    '.github/',
    'node_modules/',
    'tests/',
    'storage/backups/',
    'storage/logs/',
    'storage/sessions/',
    'storage/uploads/',
];

for ($index = 0; $index < $zip->numFiles; $index++) {
    $name = $zip->getNameIndex($index);
    if (!is_string($name)) {
        continue;
    }
    if (basename($name) === '.gitkeep' && str_starts_with($name, 'storage/')) {
        continue;
    }
    if ($name === '.env') {
        $zip->close();
        fwrite(STDERR, "Forbidden release path: .env\n");
        exit(1);
    }
    foreach ($forbidden as $path) {
        if ($name === $path || str_starts_with($name, $path)) {
            $zip->close();
            fwrite(STDERR, sprintf("Forbidden release path: %s\n", $name));
            exit(1);
        }
    }
}

$installed = $zip->getFromName('vendor/composer/installed.json');
$zip->close();
if (!is_string($installed)) {
    fwrite(STDERR, "Production dependencies are missing.\n");
    exit(1);
}

$metadata = json_decode($installed, true);
$dev = is_array($metadata) ? ($metadata['dev'] ?? null) : null;
if ($dev !== false) {
    fwrite(STDERR, "Release contains development dependency metadata.\n");
    exit(1);
}

printf("Verified %s\n", basename($archive));
