#!/usr/bin/env php
<?php

declare(strict_types=1);

const MINIMUM_PHP_VERSION = '8.2.0';

$requiredExtensions = [
    'fileinfo',
    'json',
    'mbstring',
    'openssl',
    'PDO',
    'pdo_mysql',
    'zip',
];

$optionalExtensionGroups = [
    'image processing' => ['gd', 'imagick'],
];

$errors = [];

if (version_compare(PHP_VERSION, MINIMUM_PHP_VERSION, '<')) {
    $errors[] = sprintf(
        'PHP %s or newer is required; %s is installed.',
        MINIMUM_PHP_VERSION,
        PHP_VERSION,
    );
}

foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $errors[] = sprintf('Required PHP extension "%s" is missing.', $extension);
    }
}

foreach ($optionalExtensionGroups as $capability => $extensions) {
    $available = array_filter($extensions, extension_loaded(...));

    if ($available === []) {
        $errors[] = sprintf(
            'At least one extension for %s is required (%s).',
            $capability,
            implode(' or ', $extensions),
        );
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Platform check failed:\n\n");

    foreach ($errors as $error) {
        fwrite(STDERR, sprintf("- %s\n", $error));
    }

    exit(1);
}

printf("Platform check passed with PHP %s.\n", PHP_VERSION);
