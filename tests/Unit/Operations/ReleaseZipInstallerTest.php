<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;
use ReaCms\Operations\ReleaseZipInstaller;
use RuntimeException;
use ZipArchive;

final class ReleaseZipInstallerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rea-release-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/storage', 0700, true);
        file_put_contents($this->root . '/VERSION', '1.0.0');
        file_put_contents($this->root . '/.env', 'secret');
        file_put_contents($this->root . '/storage/content', 'keep');
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->root);
    }

    public function testInstallsReleaseAndPreservesRuntimeDataWithBackup(): void
    {
        $zip = $this->archive(['storage/content' => 'overwrite']);
        self::assertSame('1.1.0', (new ReleaseZipInstaller($this->root))->install($zip, hash_file('sha256', $zip)));
        self::assertSame('1.1.0', file_get_contents($this->root . '/VERSION'));
        self::assertSame('secret', file_get_contents($this->root . '/.env'));
        self::assertSame('keep', file_get_contents($this->root . '/storage/content'));
        $backups = glob($this->root . '/storage/backups/upgrade-*/old/VERSION');
        self::assertCount(1, $backups);
        self::assertSame('1.0.0', file_get_contents($backups[0]));
        self::assertFileDoesNotExist($this->root . '/storage/upgrade-maintenance');
    }

    public function testRejectsUnsafeOrIncompleteArchivesBeforeReplacingFiles(): void
    {
        foreach (
            [['../escape' => 'bad'], ['.env' => 'bad'], ['VERSION' => '0.9.0'],
            ['vendor/autoload.php' => null]] as $extra
        ) {
            $zip = $this->archive($extra);
            try {
                (new ReleaseZipInstaller($this->root))->install($zip, hash_file('sha256', $zip));
                self::fail('Unsafe release accepted.');
            } catch (RuntimeException) {
                self::assertSame('1.0.0', file_get_contents($this->root . '/VERSION'));
                self::assertSame('secret', file_get_contents($this->root . '/.env'));
                self::assertFileDoesNotExist($this->root . '/storage/upgrade-maintenance');
            }
        }
    }

    public function testRejectsChecksumMismatch(): void
    {
        $zip = $this->archive([]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum');
        (new ReleaseZipInstaller($this->root))->install($zip, str_repeat('0', 64));
    }

    public function testRejectsSymlinkDestination(): void
    {
        symlink($this->root . '/storage', $this->root . '/app');
        $zip = $this->archive([]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('symbolic links');
        (new ReleaseZipInstaller($this->root))->install($zip, hash_file('sha256', $zip));
    }

    /** @param array<string, string|null> $extra */
    private function archive(array $extra): string
    {
        $path = $this->root . '/release.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = array_replace([
            'VERSION' => '1.1.0',
            'app/Core/Http/ApplicationFactory.php' => '<?php',
            'public/index.php' => '<?php',
            'vendor/autoload.php' => '<?php',
            'composer.json' => '{}',
            'config/bootstrap.php' => '<?php',
        ], $extra);
        foreach ($files as $name => $content) {
            if ($content !== null) {
                $zip->addFromString($name, $content);
            }
        }
        $zip->close();
        return $path;
    }
}
