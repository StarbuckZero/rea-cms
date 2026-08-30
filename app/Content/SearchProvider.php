<?php

declare(strict_types=1);

namespace ReaCms\Content;

interface SearchProvider
{
    /** @return list<array<string, mixed>> */
    public function search(string $pluginId, string $resource, string $query, string $locale, int $limit): array;
}
