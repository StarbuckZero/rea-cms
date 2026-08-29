#!/usr/bin/env php
<?php

declare(strict_types=1);

$environmentFile = dirname(__DIR__) . '/.env';

if (!is_file($environmentFile) || is_link($environmentFile)) {
    fwrite(STDERR, "Create a regular .env file from .env.example before running this command.\n");
    exit(1);
}

$contents = file_get_contents($environmentFile);
if (!is_string($contents)) {
    fwrite(STDERR, "The .env file could not be read.\n");
    exit(1);
}

$defaults = [
    'APP_KEY' => bin2hex(random_bytes(32)),
    'SESSION_SECURE_COOKIE' => 'false',
    'SESSION_LIFETIME_MINUTES' => '120',
    'MAIL_FROM' => 'no-reply@rea-cms.test',
];
$added = [];

foreach ($defaults as $key => $value) {
    if (preg_match('/^' . preg_quote($key, '/') . '=/m', $contents) === 1) {
        continue;
    }

    $contents = rtrim($contents) . PHP_EOL . $key . '=' . $value . PHP_EOL;
    $added[] = $key;
}

if (file_put_contents($environmentFile, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "The .env file could not be updated.\n");
    exit(1);
}

if ($added === []) {
    fwrite(STDOUT, "Local Phase 2 configuration is already present.\n");
    exit(0);
}

fwrite(STDOUT, 'Added local values for: ' . implode(', ', $added) . PHP_EOL);
