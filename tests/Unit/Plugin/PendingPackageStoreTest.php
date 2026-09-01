<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use ReaCms\Plugin\ManifestValidator;
use ReaCms\Plugin\PendingPackageStore;
use ReaCms\Plugin\PluginException;
use ReaCms\Plugin\StagedPackage;

final class PendingPackageStoreTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/rea-pending-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root . '/stage/notes', 0700, true));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testPendingPackageIsBoundToTheSessionAndCanOnlyBeTakenOnce(): void
    {
        $validator = new ManifestValidator();
        $manifest = $validator->validate(json_encode([
            'schemaVersion' => 1,
            'id' => 'notes',
            'name' => 'Notes',
            'version' => '1.0.0',
            'reaCmsVersion' => '^1.0',
            'description' => 'Notes.',
            'author' => 'Example',
            'tables' => ['plugin_notes_entries'],
            'permissions' => [],
        ], JSON_THROW_ON_ERROR));
        self::assertNotFalse(file_put_contents($this->root . '/stage/notes/plugin.json', json_encode(
            $manifest->document,
            JSON_THROW_ON_ERROR,
        )));
        $store = new PendingPackageStore($this->root . '/stage', $validator);
        $token = $store->put(new StagedPackage(
            $manifest,
            $this->root . '/stage/notes',
            hash('sha256', 'package'),
        ), 'session-hash');

        $package = $store->take($token, 'session-hash');
        self::assertSame('notes', $package->manifest->id);
        self::assertSame('Example', $package->manifest->author);
        self::assertSame($manifest->hash, $package->manifest->hash);

        $this->expectException(PluginException::class);
        $store->take($token, 'session-hash');
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
