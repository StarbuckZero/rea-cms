#!/usr/bin/env php
<?php

declare(strict_types=1);

use ReaCms\Database\ConnectionFactory;
use ReaCms\Database\Migrations\CoreMigrationRunner;
use ReaCms\Database\Migrations\PdoMigrationDatabase;

$projectRoot = dirname(__DIR__);

/** @var ReaCms\Core\Configuration\Environment $environment */
$environment = require $projectRoot . '/config/bootstrap.php';
$pdo = ConnectionFactory::create($environment);
$runner = new CoreMigrationRunner(
    new PdoMigrationDatabase($pdo),
    $projectRoot . '/database/migrations',
    $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_',
);

$migrated = $runner->migrate();

if ($migrated === []) {
    fwrite(STDOUT, "No pending core migrations.\n");
    exit(0);
}

foreach ($migrated as $version) {
    fwrite(STDOUT, sprintf("Applied %s.\n", $version));
}
