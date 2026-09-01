<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

final class PluginRecord
{
    /** @param list<string> $tables
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly string $state,
        public readonly string $packageHash,
        public readonly string $name = '',
        public readonly string $description = '',
        public readonly ?string $navigationLabel = null,
        public readonly ?string $navigationPath = null,
        public readonly string $author = '',
        public readonly array $tables = [],
        public readonly array $manifest = [],
    ) {
    }
}
