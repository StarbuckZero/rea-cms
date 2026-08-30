<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use ReaCms\Plugin\PluginRecord;
use ReaCms\Plugin\PluginRegistry;
use ReaCms\Plugin\StagedPackage;

final class InMemoryPluginRegistry implements PluginRegistry
{
    /** @var array<string, PluginRecord> */
    public array $records = [];
    public bool $failUpdate = false;

    public function find(string $pluginId): ?PluginRecord
    {
        return $this->records[$pluginId] ?? null;
    }

    public function install(StagedPackage $package): void
    {
        $this->records[$package->manifest->id] = new PluginRecord(
            $package->manifest->id,
            $package->manifest->version,
            'disabled',
            $package->packageHash,
        );
    }

    public function update(StagedPackage $package): void
    {
        if ($this->failUpdate) {
            throw new \RuntimeException('Simulated registry failure.');
        }
        $state = $this->records[$package->manifest->id]->state;
        $this->records[$package->manifest->id] = new PluginRecord(
            $package->manifest->id,
            $package->manifest->version,
            $state,
            $package->packageHash,
        );
    }

    public function setState(string $pluginId, string $state): void
    {
        $record = $this->records[$pluginId];
        $this->records[$pluginId] = new PluginRecord($record->id, $record->version, $state, $record->packageHash);
    }

    public function remove(string $pluginId): void
    {
        unset($this->records[$pluginId]);
    }
}
