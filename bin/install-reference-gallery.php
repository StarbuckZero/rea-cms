#!/usr/bin/env php
<?php

declare(strict_types=1);

use ReaCms\Database\ConnectionFactory;
use ReaCms\Plugin\ManifestValidator;
use ReaCms\Plugin\PdoPluginMigrationRunner;
use ReaCms\Plugin\PdoPluginRegistry;
use ReaCms\Plugin\StagedPackage;

$root = dirname(__DIR__);
/** @var ReaCms\Core\Configuration\Environment $environment */
$environment = require $root . '/config/bootstrap.php';
$directory = $root . '/plugins/gallery';
$json = file_get_contents($directory . '/plugin.json');
if (!is_string($json)) {
    throw new RuntimeException('The bundled Gallery manifest is missing.');
}
$manifest = (new ManifestValidator())->validate($json);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
$hashes = [];
foreach ($files as $file) {
    if ($file instanceof SplFileInfo && $file->isFile()) {
        $hashes[] = substr($file->getPathname(), strlen($directory)) . ':' . hash_file('sha256', $file->getPathname());
    }
}
sort($hashes, SORT_STRING);
$package = new StagedPackage($manifest, $directory, hash('sha256', implode("\n", $hashes)));
$pdo = ConnectionFactory::create($environment);
$prefix = $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_';
$registry = new PdoPluginRegistry($pdo, $prefix);
$installed = $registry->find('gallery');
if ($installed === null) {
    $registry->install($package);
} else {
    $registry->update($package);
}
try {
    (new PdoPluginMigrationRunner($pdo, prefix: $prefix))->apply($package);
} catch (Throwable $exception) {
    if ($installed === null) {
        $registry->remove('gallery');
    }
    throw $exception;
}
$options = getopt('', ['enable']);
if (array_key_exists('enable', $options)) {
    $registry->setState('gallery', 'enabled');
}
$state = array_key_exists('enable', $options) ? ' and enabled' : '';
fwrite(STDOUT, 'Gallery reference plugin is installed' . $state . ".\n");
