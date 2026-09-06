<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Setup;

use PHPUnit\Framework\TestCase;
use ReaCms\Setup\EnvironmentFile;
use ReaCms\Setup\InstallConfiguration;
use ReaCms\Setup\InstallException;
use ReaCms\Setup\Installer;
use ReaCms\Setup\InstallState;

final class InstallerTest extends TestCase
{
    public function testItRejectsConcurrentInstallationAttemptsBeforeDatabaseWork(): void
    {
        $directory = sys_get_temp_dir() . '/rea-cms-installer-' . bin2hex(random_bytes(8));
        mkdir($directory . '/storage', 0700, true);
        $lock = fopen($directory . '/storage/install.lock', 'c');
        self::assertIsResource($lock);
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));

        $installer = new Installer(
            $directory,
            new EnvironmentFile($directory),
            new InstallState($directory),
        );

        try {
            $installer->install($this->configuration(), 'request-id', '127.0.0.1');
            self::fail('The concurrent installation should have been rejected.');
        } catch (InstallException $exception) {
            self::assertStringContainsString('already running', $exception->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            unlink($directory . '/storage/install.lock');
            rmdir($directory . '/storage');
            rmdir($directory);
        }
    }

    private function configuration(): InstallConfiguration
    {
        return new InstallConfiguration(
            'https://cms.example.com',
            'UTC',
            'no-reply@example.com',
            'localhost',
            3306,
            'cms',
            'cms',
            'database password',
            'rea_',
            'admin@example.com',
            'Administrator',
            'correct horse battery staple',
        );
    }
}
