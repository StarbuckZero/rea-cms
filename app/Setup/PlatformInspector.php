<?php

declare(strict_types=1);

namespace ReaCms\Setup;

final class PlatformInspector
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @return list<PlatformRequirement>
     */
    public function inspect(): array
    {
        $requirements = [
            new PlatformRequirement(
                'PHP 8.2 or newer',
                version_compare(PHP_VERSION, '8.2.0', '>='),
                'Detected PHP ' . PHP_VERSION,
            ),
        ];

        foreach (['curl', 'dom', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'zip'] as $extension) {
            $requirements[] = new PlatformRequirement(
                'PHP extension: ' . $extension,
                extension_loaded($extension),
                extension_loaded($extension) ? 'Available' : 'Not available',
            );
        }

        $imageLibrary = extension_loaded('gd') || extension_loaded('imagick');
        $requirements[] = new PlatformRequirement(
            'Image library',
            $imageLibrary,
            $imageLibrary ? 'GD or ImageMagick is available' : 'Install GD or ImageMagick',
        );

        $storage = $this->projectRoot . '/storage';
        $requirements[] = new PlatformRequirement(
            'Writable private storage',
            is_dir($storage) && is_writable($storage),
            is_dir($storage) && is_writable($storage) ? 'Writable' : 'The storage directory is not writable',
        );
        $requirements[] = new PlatformRequirement(
            'Writable application directory',
            is_writable($this->projectRoot),
            is_writable($this->projectRoot) ? 'Writable for initial configuration' : 'Cannot create .env',
        );

        return $requirements;
    }

    public function passes(): bool
    {
        foreach ($this->inspect() as $requirement) {
            if ($requirement->required && !$requirement->passed) {
                return false;
            }
        }

        return true;
    }
}
