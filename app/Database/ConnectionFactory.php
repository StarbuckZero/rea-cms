<?php

declare(strict_types=1);

namespace ReaCms\Database;

use PDO;
use ReaCms\Core\Configuration\Environment;

final class ConnectionFactory
{
    public static function create(Environment $environment): PDO
    {
        $host = $environment->require('DB_HOST');
        $port = self::port($environment->get('DB_PORT', '3306'));
        $database = $environment->require('DB_DATABASE');
        $username = $environment->require('DB_USERNAME');
        $password = $environment->require('DB_PASSWORD');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database,
        );

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }

    private static function port(?string $port): int
    {
        $parsed = filter_var($port, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 65535,
            ],
        ]);

        if (!is_int($parsed)) {
            throw new DatabaseConfigurationException('DB_PORT must be a valid TCP port.');
        }

        return $parsed;
    }
}
