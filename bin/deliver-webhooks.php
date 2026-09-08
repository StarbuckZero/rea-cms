#!/usr/bin/env php
<?php

declare(strict_types=1);

use ReaCms\Database\ConnectionFactory;
use ReaCms\Jobs\PdoJobQueue;
use ReaCms\Webhook\CurlWebhookSender;
use ReaCms\Webhook\WebhookDelivery;
use ReaCms\Webhook\WebhookFactory;
use ReaCms\Webhook\WebhookSigner;
use ReaCms\Webhook\WebhookWorker;

/** @var ReaCms\Core\Configuration\Environment $environment */
$environment = require dirname(__DIR__) . '/config/bootstrap.php';
$options = getopt('', ['limit:']);
$limit = filter_var($options['limit'] ?? '100', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 10000],
]);
if (!is_int($limit)) {
    fwrite(STDERR, "Usage: php bin/deliver-webhooks.php [--limit=100] (1–10000 jobs)\n");
    exit(1);
}
$pdo = ConnectionFactory::create($environment);
$queue = new PdoJobQueue($pdo, $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_');
$worker = new WebhookWorker(
    $queue,
    WebhookFactory::repository($pdo, $environment),
    new WebhookDelivery(WebhookFactory::destinations(), new WebhookSigner(), new CurlWebhookSender())
);
$count = 0;
while ($count < $limit && $worker->runOne()) {
    $count++;
}
fwrite(STDOUT, sprintf("Processed %d webhook job(s).\n", $count));
