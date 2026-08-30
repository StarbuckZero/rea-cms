<?php

declare(strict_types=1);

namespace ReaCms\Content;

use ReaCms\Plugin\PluginRouteGate;

final class ContentSearch
{
    public function __construct(private readonly SearchProvider $provider, private readonly PluginRouteGate $plugins)
    {
    }

    /** @return list<array<string, mixed>> */
    public function publicSearch(
        string $pluginId,
        string $resource,
        string $query,
        string $locale,
        int $limit = 20,
    ): array {
        if (!$this->plugins->exposes($pluginId) || trim($query) === '') {
            return [];
        }
        $results = $this->provider->search($pluginId, $resource, $query, $locale, min(max($limit, 1), 100));
        return array_values(array_filter(
            $results,
            static fn (array $result): bool => ($result['status'] ?? null) === 'published'
                && ($result['visibility'] ?? 'public') === 'public'
                && ($result['locale'] ?? $locale) === $locale,
        ));
    }
}
