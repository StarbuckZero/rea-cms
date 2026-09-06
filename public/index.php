<?php

declare(strict_types=1);

use ReaCms\Core\Http\ApplicationFactory;
use ReaCms\Core\Http\Request;
use ReaCms\Core\Http\RequestId;
use ReaCms\Core\Http\Response;
use ReaCms\Core\Http\SecurityHeaders;
use ReaCms\Setup\SetupApplicationFactory;

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';
$request = Request::fromGlobals();

if (!is_file($projectRoot . '/.env')) {
    if ($request->path() !== '/install') {
        (new SecurityHeaders())->apply(
            Response::redirect('/install')
                ->withHeader('Cache-Control', 'no-store, private')
                ->withHeader('Pragma', 'no-cache'),
            RequestId::generate(),
        )->send();
    }

    SetupApplicationFactory::create($projectRoot)
        ->handle($request)
        ->send();
}

/** @var ReaCms\Core\Configuration\Environment $environment */
$environment = require $projectRoot . '/config/bootstrap.php';

ApplicationFactory::create($environment, $projectRoot)
    ->handle($request)
    ->send();
