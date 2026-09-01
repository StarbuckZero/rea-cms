<?php

declare(strict_types=1);

namespace ReaCms\Core\Theme;

final class ThemePreference
{
    private const DEFAULT = 'system';

    private const ALLOWED = [
        'system',
        'light',
        'dark',
        'high-contrast',
    ];

    public static function parse(?string $value): string
    {
        if ($value === null) {
            return self::DEFAULT;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, self::ALLOWED, true) ? $normalized : self::DEFAULT;
    }

    public static function accepts(?string $value): bool
    {
        return $value !== null && in_array(strtolower(trim($value)), self::ALLOWED, true);
    }

    /** @return list<string> */
    public static function choices(): array
    {
        return self::ALLOWED;
    }
}
