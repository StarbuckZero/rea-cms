<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

final class PluginNavigationItem
{
    public function __construct(
        public readonly string $pluginId,
        public readonly string $label,
        public readonly string $path,
    ) {
    }
}
