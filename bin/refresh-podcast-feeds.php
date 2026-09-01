#!/usr/bin/env php
<?php

declare(strict_types=1);

use ReaCms\Database\ConnectionFactory;
use ReaCms\Plugin\PdoPluginRegistry;
use ReaCms\Podcast\CurlFeedFetcher;
use ReaCms\Podcast\PdoPodcastRepository;
use ReaCms\Podcast\PodcastFeedParser;
use ReaCms\Podcast\PodcastFeedSyncService;
use ReaCms\Support\SystemClock;

$root = dirname(__DIR__);
/** @var ReaCms\Core\Configuration\Environment $environment */
$environment = require $root . '/config/bootstrap.php';
$pdo = ConnectionFactory::create($environment);
$prefix = $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_';
if ((new PdoPluginRegistry($pdo, $prefix))->find('podcast')?->state !== 'enabled') {
    fwrite(STDOUT, "Podcast plugin is not enabled; nothing to refresh.\n");
    exit(0);
}
$repository = new PdoPodcastRepository($pdo);
$sync = new PodcastFeedSyncService(
    $repository,
    new CurlFeedFetcher(),
    new PodcastFeedParser(),
    new SystemClock(),
);
$count = $sync->refreshAllDue();
fwrite(STDOUT, sprintf("Checked %d due podcast feed(s).\n", $count));
