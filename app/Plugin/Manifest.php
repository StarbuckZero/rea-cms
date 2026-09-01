<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

final class Manifest
{
    /**
     * @param list<string> $tables
     * @param list<string> $permissions
     * @param array<string, mixed> $document
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $reaCmsVersion,
        public readonly string $description,
        public readonly string $author,
        public readonly array $tables,
        public readonly array $permissions,
        public readonly array $document,
        public readonly string $hash,
    ) {
    }
}
