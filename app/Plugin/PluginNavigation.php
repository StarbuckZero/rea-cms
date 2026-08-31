<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

final class PluginNavigation
{
    /** @var list<array{string, string, string}> */
    private const CORE_ITEMS = [
        ['media', 'Media', '/cms/media'],
    ];

    public function __construct(
        private readonly PluginRegistry $plugins,
        private readonly PluginAccess $access,
    ) {
    }

    /** @return list<PluginNavigationItem> */
    public function forUser(int $userId): array
    {
        $items = [];
        $included = [];

        foreach ($this->plugins->active() as $plugin) {
            if ($plugin->navigationPath === null || !$this->access->allows($userId, $plugin->id)) {
                continue;
            }

            $label = $plugin->navigationLabel ?? $plugin->name;
            $items[] = new PluginNavigationItem(
                $plugin->id,
                $label !== '' ? $label : $plugin->id,
                $plugin->navigationPath,
            );
            $included[$plugin->id] = true;
        }

        foreach (self::CORE_ITEMS as [$pluginId, $label, $path]) {
            if (!isset($included[$pluginId]) && $this->access->allows($userId, $pluginId)) {
                $items[] = new PluginNavigationItem($pluginId, $label, $path);
            }
        }

        usort($items, static fn (PluginNavigationItem $left, PluginNavigationItem $right): int => (
            strcasecmp($left->label, $right->label)
        ));

        return $items;
    }
}
