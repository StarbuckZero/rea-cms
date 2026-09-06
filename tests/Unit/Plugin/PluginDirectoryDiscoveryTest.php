<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use ReaCms\Plugin\ManifestValidator;
use ReaCms\Plugin\PackageInspector;
use ReaCms\Plugin\PluginDirectoryDiscovery;
use ReaCms\Plugin\PluginRecord;
use ReaCms\Tests\Support\InMemoryPluginRegistry;

final class PluginDirectoryDiscoveryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rea-plugin-discovery-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testDiscoveryDistinguishesAvailableInstalledUpdateAndInvalidPlugins(): void
    {
        $this->writePlugin('available', '1.0.0');
        $this->writePlugin('enabled', '1.0.0');
        $this->writePlugin('newer', '2.0.0');
        self::assertTrue(mkdir($this->root . '/broken', 0700));
        self::assertNotFalse(file_put_contents($this->root . '/broken/plugin.json', '{'));

        $registry = new InMemoryPluginRegistry();
        $registry->records['enabled'] = $this->record('enabled', '1.0.0', 'enabled');
        $registry->records['newer'] = $this->record('newer', '1.0.0', 'disabled');
        $discovery = new PluginDirectoryDiscovery(
            $this->root,
            new PackageInspector(new ManifestValidator()),
            $registry,
        );

        $statuses = [];
        $errors = [];
        foreach ($discovery->all() as $plugin) {
            $statuses[$plugin->id] = $plugin->statusLabel;
            $errors[$plugin->id] = $plugin->error;
        }

        self::assertSame('Available', $statuses['available']);
        self::assertSame('Installed / Enabled', $statuses['enabled']);
        self::assertSame('Update Available', $statuses['newer']);
        self::assertSame('Invalid', $statuses['broken']);
        self::assertStringContainsString('not valid JSON', (string) $errors['broken']);
    }

    private function writePlugin(string $id, string $version): void
    {
        self::assertTrue(mkdir($this->root . '/' . $id, 0700));
        self::assertNotFalse(file_put_contents(
            $this->root . '/' . $id . '/plugin.json',
            json_encode([
                'schemaVersion' => 1,
                'id' => $id,
                'name' => ucfirst($id),
                'version' => $version,
                'reaCmsVersion' => '^1.0',
                'description' => '',
                'tables' => ['plugin_' . $id . '_entries'],
                'permissions' => [],
            ], JSON_THROW_ON_ERROR),
        ));
    }

    private function record(string $id, string $version, string $state): PluginRecord
    {
        return new PluginRecord(
            $id,
            $version,
            $state,
            hash('sha256', $id),
            ucfirst($id),
        );
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
            is_dir($path) && !is_link($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
