<?php

declare(strict_types=1);

use ReaCms\Core\Http\ApplicationFactory;
use ReaCms\Core\Http\Request;

$projectRoot = dirname(__DIR__);

/** @var ReaCms\Core\Configuration\Environment $environment */
$environment = require $projectRoot . '/config/bootstrap.php';

ApplicationFactory::create($environment, $projectRoot)
    ->handle(Request::fromGlobals())
    ->send();
