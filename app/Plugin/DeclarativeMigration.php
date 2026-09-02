<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use JsonException;

final class DeclarativeMigration
{
    private const TYPES = ['bigint', 'integer', 'varchar', 'text', 'datetime', 'boolean', 'json'];

    /** @return list<string> */
    public function compile(string $pluginId, Manifest $manifest, string $json): array
    {
        try {
            $document = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PluginException('A plugin migration is not valid JSON.', previous: $exception);
        }
        $operations = is_array($document) ? ($document['operations'] ?? null) : null;
        if (!is_array($operations) || !array_is_list($operations)) {
            throw new PluginException('A plugin migration must contain an operations list.');
        }

        $sql = [];
        foreach ($operations as $operation) {
            if (!is_array($operation) || array_is_list($operation)) {
                throw new PluginException('A plugin migration operation is invalid.');
            }
            $action = $operation['action'] ?? null;
            $table = $operation['table'] ?? null;
            if (
                !is_string($action) || !is_string($table) || !in_array($table, $manifest->tables, true)
                || !str_starts_with($table, 'plugin_' . $pluginId . '_')
            ) {
                throw new PluginException('A migration operation escapes the declared table namespace.');
            }
            if ($action === 'create_table') {
                $sql[] = $this->createTable($table, $operation['columns'] ?? null);
            } elseif ($action === 'add_column') {
                $sql[] = sprintf(
                    'ALTER TABLE `%s` ADD COLUMN %s',
                    $table,
                    $this->column($operation['column'] ?? null),
                );
            } elseif ($action === 'create_index') {
                $sql[] = $this->createIndex($table, $operation);
            } else {
                throw new PluginException('The migration action is not allowlisted.');
            }
        }
        return $sql;
    }

    private function createTable(string $table, mixed $columns): string
    {
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new PluginException('create_table requires columns.');
        }
        $compiled = [];
        foreach ($columns as $column) {
            $compiled[] = $this->column($column);
        }
        return sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (%s) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            $table,
            implode(', ', $compiled),
        );
    }

    private function column(mixed $column): string
    {
        if (!is_array($column) || array_is_list($column)) {
            throw new PluginException('A migration column is invalid.');
        }
        $name = $column['name'] ?? null;
        $type = $column['type'] ?? null;
        if (
            !is_string($name) || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name) !== 1
            || !is_string($type) || !in_array($type, self::TYPES, true)
        ) {
            throw new PluginException('A migration column name or type is invalid.');
        }
        $typeSql = match ($type) {
            'bigint' => 'BIGINT UNSIGNED',
            'integer' => 'INT',
            'varchar' => 'VARCHAR(' . $this->varcharLength($column['length'] ?? null) . ')',
            'text' => 'TEXT',
            'datetime' => 'TIMESTAMP(6)',
            'boolean' => 'TINYINT(1)',
            'json' => 'JSON',
        };
        $nullable = ($column['nullable'] ?? false) === true ? ' NULL' : ' NOT NULL';
        $primary = ($column['primary'] ?? false) === true ? ' PRIMARY KEY' : '';
        $auto = ($column['autoIncrement'] ?? false) === true ? ' AUTO_INCREMENT' : '';
        return sprintf('`%s` %s%s%s%s', $name, $typeSql, $nullable, $auto, $primary);
    }

    /** @param array<string, mixed> $operation */
    private function createIndex(string $table, array $operation): string
    {
        $name = $operation['name'] ?? null;
        $columns = $operation['columns'] ?? null;
        $uniqueOption = $operation['unique'] ?? false;
        if (
            !is_string($name) || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name) !== 1
            || !is_array($columns) || !array_is_list($columns) || $columns === []
            || !is_bool($uniqueOption)
        ) {
            throw new PluginException('A migration index is invalid.');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $column) !== 1) {
                throw new PluginException('A migration index column is invalid.');
            }
        }
        $unique = $uniqueOption ? 'UNIQUE ' : '';
        return sprintf(
            'CREATE %sINDEX `%s` ON `%s` (%s)',
            $unique,
            $name,
            $table,
            implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns)),
        );
    }

    private function varcharLength(mixed $length): int
    {
        return is_int($length) && $length >= 1 && $length <= 1000 ? $length : 191;
    }
}
