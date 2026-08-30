<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

interface PluginRegistry
{
    public function find(string $pluginId): ?PluginRecord;

    /** @return list<PluginRecord> */
    public function active(): array;

    public function install(StagedPackage $package): void;

    public function update(StagedPackage $package): void;

    public function setState(string $pluginId, string $state): void;

    public function remove(string $pluginId): void;
}
