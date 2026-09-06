<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

final class PluginListing
{
    /** @param list<string> $tables
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly ?string $installedVersion,
        public readonly ?string $installedState,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly string $description = '',
        public readonly string $author = '',
        public readonly array $tables = [],
        public readonly array $manifest = [],
        public readonly ?string $error = null,
        public readonly bool $filesDiscovered = false,
    ) {
    }
}
