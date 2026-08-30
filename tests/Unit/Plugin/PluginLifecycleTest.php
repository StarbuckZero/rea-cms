<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use ReaCms\Plugin\ManifestValidator;
use ReaCms\Plugin\PluginException;
use ReaCms\Plugin\PluginLifecycle;
use ReaCms\Plugin\PluginRecord;
use ReaCms\Plugin\PluginRouteGate;
use ReaCms\Plugin\StagedPackage;
use ReaCms\Tests\Support\InMemoryAuditLogger;
use ReaCms\Tests\Support\InMemoryPluginRegistry;

final class PluginLifecycleTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rea-lifecycle-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testInstalledPluginStartsDisabledAndRoutesRequireEnabledState(): void
    {
        $registry = new InMemoryPluginRegistry();
        $lifecycle = $this->lifecycle($registry);
        $package = $this->package('1.0.0', 'initial');

        $lifecycle->install($package, 1, '127.0.0.1', str_repeat('a', 32));
        self::assertFalse((new PluginRouteGate($registry))->exposes('notes'));

        $lifecycle->enable('notes', 1, '127.0.0.1', str_repeat('b', 32));
        self::assertTrue((new PluginRouteGate($registry))->exposes('notes'));

        $lifecycle->disable('notes', 1, '127.0.0.1', str_repeat('c', 32));
        self::assertFalse((new PluginRouteGate($registry))->exposes('notes'));
    }

    public function testFailedUpdateRestoresPreviousWorkingDirectoryAndState(): void
    {
        $registry = new InMemoryPluginRegistry();
        $registry->records['notes'] = new PluginRecord('notes', '1.0.0', 'enabled', hash('sha256', 'old'));
        $active = $this->root . '/plugins/notes';
        self::assertTrue(mkdir($active, 0700, true));
        self::assertNotFalse(file_put_contents($active . '/version.txt', 'old'));
        $registry->failUpdate = true;

        try {
            $this->lifecycle($registry)->update(
                $this->package('1.1.0', 'new'),
                1,
                '127.0.0.1',
                str_repeat('d', 32),
            );
            self::fail('The simulated update should fail.');
        } catch (PluginException) {
            self::assertSame('old', file_get_contents($active . '/version.txt'));
            self::assertSame('enabled', $registry->records['notes']->state);
            self::assertSame('1.0.0', $registry->records['notes']->version);
        }
    }

    private function lifecycle(InMemoryPluginRegistry $registry): PluginLifecycle
    {
        return new PluginLifecycle(
            $registry,
            new InMemoryAuditLogger(),
            $this->root . '/plugins',
            $this->root . '/backups',
            $this->root . '/cache',
        );
    }

    private function package(string $version, string $contents): StagedPackage
    {
        $stage = $this->root . '/stage-' . bin2hex(random_bytes(4)) . '/notes';
        self::assertTrue(mkdir($stage, 0700, true));
        self::assertNotFalse(file_put_contents($stage . '/version.txt', $contents));
        $manifest = (new ManifestValidator())->validate(json_encode([
            'schemaVersion' => 1, 'id' => 'notes', 'name' => 'Notes', 'version' => $version,
            'reaCmsVersion' => '^1.0', 'description' => '', 'tables' => ['plugin_notes_entries'],
            'permissions' => [],
        ], JSON_THROW_ON_ERROR));
        return new StagedPackage($manifest, $stage, hash('sha256', $contents));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
