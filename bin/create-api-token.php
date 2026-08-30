#!/usr/bin/env php
<?php

declare(strict_types=1);

use ReaCms\Api\Policy\NetworkMatcher;
use ReaCms\Api\Token\ApiTokenAuthenticator;
use ReaCms\Database\ConnectionFactory;

$projectRoot = dirname(__DIR__);

/** @var ReaCms\Core\Configuration\Environment $environment */
$environment = require $projectRoot . '/config/bootstrap.php';
$options = getopt('', ['name:', 'scopes:', 'user-id::', 'ip-cidr::']);
$name = is_string($options['name'] ?? null) ? trim($options['name']) : '';
$scopeValue = is_string($options['scopes'] ?? null) ? $options['scopes'] : '';
$scopes = array_values(array_unique(array_filter(array_map('trim', explode(',', $scopeValue)))));
$userIdValue = $options['user-id'] ?? null;
$userId = is_string($userIdValue) ? filter_var($userIdValue, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]) : null;
$cidr = is_string($options['ip-cidr'] ?? null) ? trim($options['ip-cidr']) : null;

if ($name === '' || strlen($name) > 191 || $scopes === []) {
    fwrite(STDERR, "Usage: php bin/create-api-token.php --name='Deployment' --scopes='blog:read' "
        . "[--user-id=1] [--ip-cidr='192.0.2.0/24']\n");
    exit(1);
}

foreach ($scopes as $scope) {
    if (preg_match('/^[a-z][a-z0-9._-]*:[a-z][a-z0-9._-]*$/D', $scope) !== 1) {
        fwrite(STDERR, "Every scope must use resource:action syntax.\n");
        exit(1);
    }
}

if ($userIdValue !== null && !is_int($userId)) {
    fwrite(STDERR, "--user-id must be a positive integer.\n");
    exit(1);
}

if ($cidr !== null) {
    $network = explode('/', $cidr, 2)[0];
    if (!(new NetworkMatcher())->contains($cidr, $network)) {
        fwrite(STDERR, "--ip-cidr must be a valid IPv4 or IPv6 network.\n");
        exit(1);
    }
}

$pdo = ConnectionFactory::create($environment);
$prefix = $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_';
if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
    fwrite(STDERR, "The database table prefix is invalid.\n");
    exit(1);
}

$generated = ApiTokenAuthenticator::generate();
$pdo->beginTransaction();
try {
    $statement = $pdo->prepare(sprintf(
        'INSERT INTO `%sapi_tokens` (token_id, token_hash, name, user_id, ip_cidr) '
        . 'VALUES (:token_id, :token_hash, :name, :user_id, :ip_cidr)',
        $prefix,
    ));
    $statement->execute([
        'token_id' => $generated['tokenId'],
        'token_hash' => $generated['hash'],
        'name' => $name,
        'user_id' => $userId,
        'ip_cidr' => $cidr,
    ]);
    $databaseId = (int) $pdo->lastInsertId();
    $scopeStatement = $pdo->prepare(sprintf(
        'INSERT INTO `%sapi_token_scopes` (token_id, scope) VALUES (:token_id, :scope)',
        $prefix,
    ));
    foreach ($scopes as $scope) {
        $scopeStatement->execute(['token_id' => $databaseId, 'scope' => $scope]);
    }
    $pdo->commit();
} catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
}

fwrite(STDOUT, "API token created. Copy it now; it cannot be recovered:\n" . $generated['plaintext'] . "\n");
