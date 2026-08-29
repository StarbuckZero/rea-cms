#!/usr/bin/env php
<?php

declare(strict_types=1);

use ReaCms\Audit\PdoAuditLogger;
use ReaCms\Auth\PasswordHasher;
use ReaCms\Auth\PdoUserRepository;
use ReaCms\Database\ConnectionFactory;

$projectRoot = dirname(__DIR__);

/** @var ReaCms\Core\Configuration\Environment $environment */
$environment = require $projectRoot . '/config/bootstrap.php';
$options = getopt('', ['email:', 'name:']);
$email = is_string($options['email'] ?? null) ? strtolower(trim($options['email'])) : '';
$name = is_string($options['name'] ?? null) ? trim($options['name']) : '';
$password = getenv('REA_ADMIN_PASSWORD');

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $name === '') {
    fwrite(STDERR, "Usage: REA_ADMIN_PASSWORD='...' php bin/create-admin.php --email=user@example.com --name='Name'\n");
    exit(1);
}

if (!is_string($password) || strlen($password) < 12 || strlen($password) > 1024) {
    fwrite(STDERR, "REA_ADMIN_PASSWORD must contain between 12 and 1024 characters.\n");
    exit(1);
}

$pdo = ConnectionFactory::create($environment);
$prefix = $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_';
$users = new PdoUserRepository($pdo, $prefix);

if ($users->findByEmail($email) !== null) {
    fwrite(STDERR, "An account with that email already exists.\n");
    exit(1);
}

$userId = $users->create($email, (new PasswordHasher())->hash($password), $name);
$users->assignRole($userId, 'super-administrator');
(new PdoAuditLogger($pdo, $prefix))->record(
    'user.bootstrap_admin_created',
    $userId,
    '127.0.0.1',
    bin2hex(random_bytes(16)),
    [],
    'user',
    (string) $userId,
);

fwrite(STDOUT, sprintf("Created super administrator %s.\n", $email));
