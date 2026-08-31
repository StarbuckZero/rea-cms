<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use ReaCms\Plugin\PluginAccess;

final class InMemoryPluginAccess implements PluginAccess
{
    /** @var array<int, list<string>> */
    public array $assignments = [];
    public bool $allowAll = true;

    public function allows(int $userId, string $pluginId): bool
    {
        return $this->allowAll || in_array($pluginId, $this->assignments[$userId] ?? [], true);
    }

    public function assignedTo(int $userId): array
    {
        return $this->assignments[$userId] ?? [];
    }

    public function replaceForUser(int $userId, array $pluginIds): void
    {
        $this->assignments[$userId] = array_values(array_unique($pluginIds));
    }
}
