<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

final class PluginRouteGate
{
    public function __construct(private readonly PluginRegistry $plugins)
    {
    }

    public function exposes(string $pluginId): bool
    {
        return $this->plugins->find($pluginId)?->state === 'enabled';
    }
}
