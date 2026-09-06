<?php

declare(strict_types=1);

use ReaCms\Core\Configuration\Environment;

require_once dirname(__DIR__) . '/vendor/autoload.php';

return Environment::load(dirname(__DIR__));
