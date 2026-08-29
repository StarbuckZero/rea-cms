<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Core\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReaCms\Core\Configuration\Environment;
use RuntimeException;

final class EnvironmentTest extends TestCase
{
    public function testItReturnsValuesAndDefaults(): void
    {
        $environment = Environment::fromArray(['APP_ENV' => 'testing']);

        self::assertSame('testing', $environment->get('APP_ENV'));
        self::assertSame('fallback', $environment->get('MISSING', 'fallback'));
        self::assertNull($environment->get('MISSING'));
    }

    public function testItIgnoresNonStringValues(): void
    {
        $environment = Environment::fromArray([
            'STRING_VALUE' => 'kept',
            'ARRAY_VALUE' => ['not', 'allowed'],
            0 => 'invalid key',
        ]);

        self::assertSame('kept', $environment->get('STRING_VALUE'));
        self::assertNull($environment->get('ARRAY_VALUE'));
    }

    public function testItRequiresNonEmptyValues(): void
    {
        $environment = Environment::fromArray(['DB_DATABASE' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Required environment value "DB_DATABASE" is missing.');

        $environment->require('DB_DATABASE');
    }

    #[DataProvider('booleanValues')]
    public function testItParsesBooleanValues(string $value, bool $expected): void
    {
        $environment = Environment::fromArray(['FLAG' => $value]);

        self::assertSame($expected, $environment->bool('FLAG'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function booleanValues(): iterable
    {
        yield 'true' => ['true', true];
        yield 'one' => ['1', true];
        yield 'yes' => ['yes', true];
        yield 'false' => ['false', false];
        yield 'zero' => ['0', false];
        yield 'no' => ['no', false];
    }

    public function testItRejectsInvalidBooleanValues(): void
    {
        $environment = Environment::fromArray(['FLAG' => 'sometimes']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Environment value "FLAG" must be a boolean.');

        $environment->bool('FLAG');
    }
}
