<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use Throwable;

final class PluginDirectoryDiscovery
{
    public function __construct(
        private readonly string $pluginRoot,
        private readonly PackageInspector $packages,
        private readonly PluginRegistry $registry,
    ) {
    }

    /** @return list<PluginListing> */
    public function all(): array
    {
        $installed = [];
        foreach ($this->registry->all() as $record) {
            $installed[$record->id] = $record;
        }

        $listings = [];
        foreach ($this->directories() as $directoryName) {
            $record = $installed[$directoryName] ?? null;
            try {
                $package = $this->inspect($directoryName);
                $record = $installed[$package->manifest->id] ?? null;
                $listings[] = $this->listing($package, $record);
                unset($installed[$package->manifest->id]);
            } catch (Throwable $exception) {
                $listings[] = new PluginListing(
                    $directoryName,
                    $record?->name ?: $directoryName,
                    $record?->version ?: 'Unknown',
                    $record?->version,
                    $record?->state,
                    'invalid',
                    'Invalid',
                    $record?->description ?: '',
                    $record?->author ?: '',
                    $record === null ? [] : $record->tables,
                    $record === null ? [] : $record->manifest,
                    $exception->getMessage(),
                    true,
                );
                unset($installed[$directoryName]);
            }
        }

        foreach ($installed as $record) {
            $listings[] = $this->installedListing($record);
        }
        usort($listings, static fn (PluginListing $left, PluginListing $right): int =>
            strcasecmp($left->name, $right->name) ?: strcmp($left->id, $right->id));

        return $listings;
    }

    public function inspect(string $pluginId): StagedPackage
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $pluginId) !== 1) {
            throw new PluginException('The plugin ID is invalid.');
        }
        $directory = rtrim($this->pluginRoot, '/') . '/' . $pluginId;
        try {
            $package = $this->packages->inspectDirectory($directory);
        } catch (PluginException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PluginException(
                'The plugin directory could not be safely inspected.',
                previous: $exception,
            );
        }
        if ($package->manifest->id !== $pluginId) {
            throw new PluginException('The plugin directory conflicts with another plugin ID.');
        }
        return $package;
    }

    /** @return list<string> */
    private function directories(): array
    {
        if (!is_dir($this->pluginRoot)) {
            return [];
        }
        $entries = scandir($this->pluginRoot);
        if (!is_array($entries)) {
            throw new PluginException('The plugins directory could not be scanned.');
        }
        $directories = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $path = rtrim($this->pluginRoot, '/') . '/' . $entry;
            if (is_dir($path) || is_link($path)) {
                $directories[] = $entry;
            }
        }
        sort($directories, SORT_STRING);
        return $directories;
    }

    private function listing(StagedPackage $package, ?PluginRecord $record): PluginListing
    {
        $manifest = $package->manifest;
        if ($record === null) {
            return new PluginListing(
                $manifest->id,
                $manifest->name,
                $manifest->version,
                null,
                null,
                'available',
                'Available',
                $manifest->description,
                $manifest->author,
                $manifest->tables,
                $manifest->document,
                filesDiscovered: true,
            );
        }

        if (version_compare($manifest->version, $record->version, '>')) {
            return new PluginListing(
                $manifest->id,
                $manifest->name,
                $manifest->version,
                $record->version,
                $record->state,
                'update_available',
                'Update Available',
                $manifest->description,
                $manifest->author,
                $manifest->tables,
                $manifest->document,
                filesDiscovered: true,
            );
        }

        return $this->installedListing($record, true);
    }

    private function installedListing(PluginRecord $record, bool $filesDiscovered = false): PluginListing
    {
        $statusLabel = match ($record->state) {
            'enabled' => 'Installed / Enabled',
            'disabled' => 'Installed / Disabled',
            'maintenance' => 'Installed / Maintenance',
            'uninstalled' => 'Uninstalled',
            default => 'Installed / ' . ucfirst($record->state),
        };

        return new PluginListing(
            $record->id,
            $record->name ?: $record->id,
            $record->version,
            $record->version,
            $record->state,
            $record->state,
            $statusLabel,
            $record->description,
            $record->author,
            $record->tables,
            $record->manifest,
            filesDiscovered: $filesDiscovered,
        );
    }
}
