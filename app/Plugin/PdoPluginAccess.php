<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use PDO;
use ReaCms\Auth\Authorization;
use RuntimeException;

final class PdoPluginAccess implements PluginAccess
{
    private readonly string $access;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Authorization $authorization,
        string $prefix = 'rea_',
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }
        $this->access = $prefix . 'user_plugin_access';
    }

    public function allows(int $userId, string $pluginId): bool
    {
        if ($this->authorization->allows($userId, 'core.admin.access')) {
            return true;
        }
        $statement = $this->pdo->prepare(sprintf(
            'SELECT 1 FROM `%s` WHERE user_id = :user_id AND plugin_id = :plugin_id LIMIT 1',
            $this->access,
        ));
        $statement->execute(['user_id' => $userId, 'plugin_id' => $pluginId]);
        return $statement->fetchColumn() !== false;
    }

    public function assignedTo(int $userId): array
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT plugin_id FROM `%s` WHERE user_id = :user_id ORDER BY plugin_id',
            $this->access,
        ));
        $statement->execute(['user_id' => $userId]);
        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function replaceForUser(int $userId, array $pluginIds): void
    {
        $pluginIds = array_values(array_unique(array_filter($pluginIds, static fn (string $id): bool => (
            preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $id) === 1
        ))));
        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare(sprintf('DELETE FROM `%s` WHERE user_id = :user_id', $this->access));
            $delete->execute(['user_id' => $userId]);
            $insert = $this->pdo->prepare(sprintf(
                'INSERT INTO `%s` (user_id, plugin_id) VALUES (:user_id, :plugin_id)',
                $this->access,
            ));
            foreach ($pluginIds as $pluginId) {
                $insert->execute(['user_id' => $userId, 'plugin_id' => $pluginId]);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
