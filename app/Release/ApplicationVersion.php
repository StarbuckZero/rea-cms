<?php

declare(strict_types=1);

namespace ReaCms\Release;

final class ApplicationVersion
{
    public static function detect(string $projectRoot): string
    {
        $path = rtrim($projectRoot, DIRECTORY_SEPARATOR) . '/VERSION';
        $version = is_file($path) ? file_get_contents($path) : false;

        return is_string($version) && trim($version) !== '' ? trim($version) : 'development';
    }
}
