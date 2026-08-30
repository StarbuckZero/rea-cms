<?php

declare(strict_types=1);

namespace ReaCms\Content;

use ReaCms\Auth\Authorization;
use ReaCms\Plugin\PluginRouteGate;

final class ContentService
{
    public function __construct(
        private readonly ContentRepository $contents,
        private readonly ContentValidator $validator,
        private readonly Authorization $authorization,
        private readonly PluginRouteGate $plugins,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function create(ResourceDefinition $definition, int $userId, array $input): int
    {
        $this->authorize($definition, $userId, 0);
        return $this->contents->create($definition, $this->validator->validate($definition, $input));
    }

    /** @param array<string, mixed> $input */
    public function update(ResourceDefinition $definition, int $userId, int $id, array $input): void
    {
        $this->authorize($definition, $userId, 1);
        if ($this->contents->find($definition, $id) === null) {
            throw new ContentException('The content record was not found.');
        }
        $this->contents->update($definition, $id, $this->validator->validate($definition, $input));
    }

    private function authorize(ResourceDefinition $definition, int $userId, int $permissionIndex): void
    {
        $permission = $definition->permissions[$permissionIndex] ?? null;
        if (
            !$this->plugins->exposes($definition->pluginId) || $permission === null
            || !$this->authorization->allows($userId, $permission)
        ) {
            throw new ContentException('Content access was denied.');
        }
    }
}
