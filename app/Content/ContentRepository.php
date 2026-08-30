<?php

declare(strict_types=1);

namespace ReaCms\Content;

interface ContentRepository
{
    /** @return array<string, mixed>|null */
    public function find(ResourceDefinition $definition, int $id): ?array;

    /** @param array<string, mixed> $values */
    public function create(ResourceDefinition $definition, array $values): int;

    /** @param array<string, mixed> $values */
    public function update(ResourceDefinition $definition, int $id, array $values): void;
}
