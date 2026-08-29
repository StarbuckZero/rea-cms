<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use ReaCms\Core\Configuration\Environment;
use ReaCms\Database\ConnectionFactory;
use ReaCms\Database\DatabaseConfigurationException;

final class ConnectionFactoryTest extends TestCase
{
    public function testItRejectsAnInvalidDatabasePortBeforeConnecting(): void
    {
        $environment = Environment::fromArray([
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '70000',
            'DB_DATABASE' => 'rea_cms_test',
            'DB_USERNAME' => 'rea_cms_test',
            'DB_PASSWORD' => 'not-a-real-secret',
        ]);

        $this->expectException(DatabaseConfigurationException::class);

        ConnectionFactory::create($environment);
    }
}
