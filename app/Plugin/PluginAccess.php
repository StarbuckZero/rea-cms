<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

interface PluginAccess
{
    public function allows(int $userId, string $pluginId): bool;

    /** @return list<string> */
    public function assignedTo(int $userId): array;

    /** @param list<string> $pluginIds */
    public function replaceForUser(int $userId, array $pluginIds): void;
}
