<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use ReaCms\Audit\AuditLogger;
use Throwable;

final class PluginLifecycle
{
    /** @var callable(StagedPackage): void */
    private $migrate;

    public function __construct(
        private readonly PluginRegistry $registry,
        private readonly AuditLogger $audit,
        private readonly string $pluginRoot,
        private readonly string $backupRoot,
        private readonly string $cacheRoot,
        ?callable $migrate = null,
    ) {
        $this->migrate = $migrate ?? static function (StagedPackage $package): void {
        };
    }

    public function install(StagedPackage $package, int $actorId, string $ip, string $requestId): void
    {
        if ($this->registry->find($package->manifest->id) !== null) {
            throw new PluginException('The plugin is already installed.');
        }
        $destination = $this->path($package->manifest->id);
        $this->ensureDirectory($this->pluginRoot);
        if (file_exists($destination) || !rename($package->directory, $destination)) {
            throw new PluginException('The staged plugin could not be atomically installed.');
        }
        $installedPackage = new StagedPackage(
            $package->manifest,
            $destination,
            $package->packageHash,
        );
        try {
            $this->registry->install($installedPackage);
            ($this->migrate)($installedPackage);
            $this->clearCaches();
        } catch (Throwable $exception) {
            $this->registry->remove($package->manifest->id);
            rename($destination, $package->directory);
            throw new PluginException('Plugin installation was rolled back.', previous: $exception);
        }
        $this->record('plugin.installed', $package->manifest->id, $actorId, $ip, $requestId);
    }

    public function update(StagedPackage $package, int $actorId, string $ip, string $requestId): void
    {
        $current = $this->requireInstalled($package->manifest->id);
        if (version_compare($package->manifest->version, $current->version, '<=')) {
            throw new PluginException('Plugin updates must increase the semantic version.');
        }
        $active = $this->path($package->manifest->id);
        $this->ensureDirectory($this->backupRoot);
        $backup = rtrim($this->backupRoot, '/') . '/' . $package->manifest->id . '-' . bin2hex(random_bytes(8));
        $this->registry->setState($package->manifest->id, 'maintenance');
        try {
            ($this->migrate)($package);
        } catch (Throwable $exception) {
            $this->registry->setState($package->manifest->id, $current->state);
            throw new PluginException('Plugin migrations failed before activation.', previous: $exception);
        }
        if (!rename($active, $backup) || !rename($package->directory, $active)) {
            if (is_dir($backup) && !is_dir($active)) {
                rename($backup, $active);
            }
            $this->registry->setState($package->manifest->id, $current->state);
            throw new PluginException('The plugin update could not switch directories.');
        }
        try {
            $this->registry->update($package);
            $this->registry->setState($package->manifest->id, $current->state);
            $this->clearCaches();
        } catch (Throwable $exception) {
            rename($active, $package->directory);
            rename($backup, $active);
            $this->registry->setState($package->manifest->id, $current->state);
            throw new PluginException('The failed plugin update restored the previous version.', previous: $exception);
        }
        $this->record('plugin.updated', $package->manifest->id, $actorId, $ip, $requestId);
    }

    public function enable(string $pluginId, int $actorId, string $ip, string $requestId): void
    {
        $record = $this->requireInstalled($pluginId);
        if ($record->state !== 'disabled') {
            throw new PluginException('Only a disabled plugin can be enabled.');
        }
        if (!is_dir($this->path($pluginId))) {
            throw new PluginException('Plugin files are missing.');
        }
        $this->registry->setState($pluginId, 'enabled');
        $this->clearCaches();
        $this->record('plugin.enabled', $pluginId, $actorId, $ip, $requestId);
    }

    public function disable(string $pluginId, int $actorId, string $ip, string $requestId): void
    {
        $record = $this->requireInstalled($pluginId);
        if ($record->state !== 'enabled') {
            throw new PluginException('Only an enabled plugin can be disabled.');
        }
        $this->registry->setState($pluginId, 'disabled');
        $this->clearCaches();
        $this->record('plugin.disabled', $pluginId, $actorId, $ip, $requestId);
    }

    public function uninstall(string $pluginId, int $actorId, string $ip, string $requestId): void
    {
        $this->requireInstalled($pluginId);
        $source = $this->path($pluginId);
        $this->ensureDirectory($this->backupRoot);
        $preserved = rtrim($this->backupRoot, '/') . '/uninstalled-' . $pluginId . '-' . bin2hex(random_bytes(8));
        if (is_dir($source) && !rename($source, $preserved)) {
            throw new PluginException('Plugin files could not be removed from service.');
        }
        $this->registry->setState($pluginId, 'uninstalled');
        $this->clearCaches();
        $this->record('plugin.uninstalled_data_preserved', $pluginId, $actorId, $ip, $requestId);
    }

    /**
     * @param callable(PluginRecord): string $export
     * @param callable(PluginRecord): void $dropTables
     */
    public function purge(
        string $pluginId,
        string $confirmation,
        callable $export,
        callable $dropTables,
        int $actorId,
        string $ip,
        string $requestId,
    ): string {
        $record = $this->requireInstalled($pluginId);
        if ($record->state !== 'uninstalled' || !hash_equals('PURGE ' . $pluginId, $confirmation)) {
            throw new PluginException('Purge requires an uninstalled plugin and exact typed confirmation.');
        }
        $exportPath = $export($record);
        if ($exportPath === '' || !is_file($exportPath)) {
            throw new PluginException('Purge was stopped because the final export failed.');
        }
        $dropTables($record);
        $this->registry->remove($pluginId);
        $this->clearCaches();
        $this->audit->record(
            'plugin.purged',
            $actorId,
            $ip,
            $requestId,
            ['export' => basename($exportPath)],
            'plugin',
            $pluginId,
        );

        return $exportPath;
    }

    private function requireInstalled(string $pluginId): PluginRecord
    {
        $record = $this->registry->find($pluginId);
        if ($record === null) {
            throw new PluginException('The plugin is not installed.');
        }
        return $record;
    }

    private function path(string $pluginId): string
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $pluginId) !== 1) {
            throw new PluginException('The plugin ID is invalid.');
        }
        return rtrim($this->pluginRoot, '/') . '/' . $pluginId;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new PluginException('A private plugin directory could not be created.');
        }
    }

    private function clearCaches(): void
    {
        $this->ensureDirectory($this->cacheRoot);
        foreach (glob(rtrim($this->cacheRoot, '/') . '/plugin-*') ?: [] as $cache) {
            if (is_file($cache)) {
                unlink($cache);
            }
        }
    }

    private function record(string $event, string $pluginId, int $actorId, string $ip, string $requestId): void
    {
        $this->audit->record($event, $actorId, $ip, $requestId, [], 'plugin', $pluginId);
    }
}
