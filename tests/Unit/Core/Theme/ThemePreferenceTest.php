<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Core\Theme;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReaCms\Core\Theme\ThemePreference;

final class ThemePreferenceTest extends TestCase
{
    #[DataProvider('validThemes')]
    public function testItAcceptsEverySupportedTheme(string $theme): void
    {
        self::assertSame($theme, ThemePreference::parse($theme));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validThemes(): iterable
    {
        yield 'system' => ['system'];
        yield 'light' => ['light'];
        yield 'dark' => ['dark'];
        yield 'high contrast' => ['high-contrast'];
    }

    public function testItNormalizesCaseAndWhitespace(): void
    {
        self::assertSame('dark', ThemePreference::parse(' DARK '));
    }

    public function testItDefaultsInvalidOrMissingPreferencesToSystem(): void
    {
        self::assertSame('system', ThemePreference::parse(null));
        self::assertSame('system', ThemePreference::parse('unknown'));
    }
}
