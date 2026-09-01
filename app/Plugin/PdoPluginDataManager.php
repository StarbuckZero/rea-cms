<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use JsonException;
use PDO;
use PDOStatement;
use RuntimeException;

final class PdoPluginDataManager implements PluginDataManager
{
    private readonly string $backupTable;
    private readonly string $prefix;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $backupRoot,
        string $prefix = 'rea_',
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }
        $this->prefix = $prefix;
        $this->backupTable = $prefix . 'plugin_backups';
    }

    public function summarize(PluginRecord $plugin): PluginDataSummary
    {
        $rows = [];
        foreach ($this->validatedTables($plugin) as $table) {
            $statement = $this->query(sprintf('SELECT COUNT(*) FROM `%s`', $table));
            $rows[$table] = (int) $statement->fetchColumn();
        }
        foreach ($this->coreData($plugin->id) as $name => $records) {
            if ($records !== []) {
                $rows['core:' . $name] = count($records);
            }
        }
        return new PluginDataSummary($rows);
    }

    public function export(PluginRecord $plugin): string
    {
        $tables = [];
        foreach ($this->validatedTables($plugin) as $table) {
            $schema = $this->query(sprintf('SHOW CREATE TABLE `%s`', $table))->fetch();
            $tables[$table] = [
                'schema' => is_array($schema) ? (string) (array_values($schema)[1] ?? '') : '',
                'rows' => $this->query(sprintf('SELECT * FROM `%s`', $table))->fetchAll(),
            ];
        }
        $payload = [
            'formatVersion' => 1,
            'scope' => 'plugin',
            'createdAt' => gmdate(DATE_ATOM),
            'plugin' => [
                'id' => $plugin->id,
                'name' => $plugin->name,
                'version' => $plugin->version,
                'packageHash' => $plugin->packageHash,
                'manifest' => $plugin->manifest,
            ],
            'tables' => $tables,
            'coreData' => $this->coreData($plugin->id),
            'media' => $this->mediaManifest($plugin->id),
        ];
        try {
            $canonical = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $document = json_encode([
                'checksum' => hash('sha256', $canonical),
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (JsonException $exception) {
            throw new PluginException('Plugin data could not be serialized for backup.', previous: $exception);
        }

        $directory = rtrim($this->backupRoot, '/') . '/' . $plugin->id;
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new PluginException('Private plugin backup storage could not be created.');
        }
        $path = $directory . '/data-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.json';
        if (file_put_contents($path, $document, LOCK_EX) === false || !chmod($path, 0600)) {
            throw new PluginException('The plugin data backup could not be stored.');
        }

        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s` (plugin_id, version, backup_path, package_hash) '
            . 'VALUES (:plugin_id, :version, :backup_path, :package_hash)',
            $this->backupTable,
        ));
        $statement->execute([
            'plugin_id' => $plugin->id,
            'version' => $plugin->version,
            'backup_path' => $path,
            'package_hash' => $plugin->packageHash,
        ]);

        return $path;
    }

    public function purge(PluginRecord $plugin): void
    {
        $this->purgeCoreData($plugin->id);
        $tables = $this->validatedTables($plugin);
        if ($tables === []) {
            return;
        }
        $quoted = array_map(static fn (string $table): string => '`' . $table . '`', $tables);
        $this->pdo->exec('DROP TABLE IF EXISTS ' . implode(', ', $quoted));
    }

    /** @return list<string> */
    private function validatedTables(PluginRecord $plugin): array
    {
        foreach ($plugin->tables as $table) {
            if (preg_match('/^plugin_' . preg_quote($plugin->id, '/') . '_[a-z][a-z0-9_]{0,47}$/D', $table) !== 1) {
                throw new PluginException('A plugin data operation escaped the plugin table namespace.');
            }
        }
        return array_values(array_unique($plugin->tables));
    }

    private function query(string $sql): PDOStatement
    {
        $statement = $this->pdo->query($sql);
        if (!$statement instanceof PDOStatement) {
            throw new PluginException('A plugin data query could not be started.');
        }
        return $statement;
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function coreData(string $pluginId): array
    {
        $taxonomies = $this->rows(
            sprintf('SELECT * FROM `%staxonomies` WHERE plugin_id = :plugin_id', $this->prefix),
            ['plugin_id' => $pluginId],
        );
        $taxonomyIds = array_values(array_filter(array_map(
            static fn (array $row): mixed => $row['id'] ?? null,
            $taxonomies,
        ), 'is_int'));
        $terms = $this->rowsForIds($this->prefix . 'terms', 'taxonomy_id', $taxonomyIds);
        $termIds = array_values(array_filter(array_map(
            static fn (array $row): mixed => $row['id'] ?? null,
            $terms,
        ), 'is_int'));

        return [
            'content_revisions' => $this->pluginRows('content_revisions', $pluginId),
            'content_slug_history' => $this->pluginRows('content_slug_history', $pluginId),
            'content_previews' => $this->pluginRows('content_previews', $pluginId),
            'taxonomies' => $taxonomies,
            'terms' => $terms,
            'term_relationships' => $this->relatedRows(
                'term_relationships',
                'plugin_id',
                $pluginId,
                'term_id',
                $termIds,
            ),
            'content_relationships' => $this->rows(
                sprintf(
                    'SELECT * FROM `%scontent_relationships` '
                    . 'WHERE source_plugin_id = :plugin_id OR target_plugin_id = :plugin_id',
                    $this->prefix,
                ),
                ['plugin_id' => $pluginId],
            ),
            'redirects' => $this->pluginRows('redirects', $pluginId),
            'media_usage' => $this->pluginRows('media_usage', $pluginId),
        ];
    }

    /** @return array{records: list<array<string, mixed>>, variants: list<array<string, mixed>>} */
    private function mediaManifest(string $pluginId): array
    {
        $usage = $this->pluginRows('media_usage', $pluginId);
        $mediaIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): mixed => $row['media_id'] ?? null,
            $usage,
        ), 'is_int')));
        return [
            'records' => $this->rowsForIds($this->prefix . 'media', 'id', $mediaIds),
            'variants' => $this->rowsForIds($this->prefix . 'media_variants', 'media_id', $mediaIds),
        ];
    }

    private function purgeCoreData(string $pluginId): void
    {
        $taxonomyRows = $this->pluginRows('taxonomies', $pluginId);
        $taxonomyIds = array_values(array_filter(array_map(
            static fn (array $row): mixed => $row['id'] ?? null,
            $taxonomyRows,
        ), 'is_int'));
        $terms = $this->rowsForIds($this->prefix . 'terms', 'taxonomy_id', $taxonomyIds);
        $termIds = array_values(array_filter(array_map(
            static fn (array $row): mixed => $row['id'] ?? null,
            $terms,
        ), 'is_int'));

        $this->deleteRelatedRows('term_relationships', 'plugin_id', $pluginId, 'term_id', $termIds);
        $this->execute(
            sprintf(
                'DELETE FROM `%scontent_relationships` '
                . 'WHERE source_plugin_id = :plugin_id OR target_plugin_id = :plugin_id',
                $this->prefix,
            ),
            ['plugin_id' => $pluginId],
        );
        $pluginScopedTables = [
            'content_revisions',
            'content_slug_history',
            'content_previews',
            'redirects',
            'media_usage',
            'taxonomies',
        ];
        foreach ($pluginScopedTables as $table) {
            $this->execute(
                sprintf('DELETE FROM `%s%s` WHERE plugin_id = :plugin_id', $this->prefix, $table),
                ['plugin_id' => $pluginId],
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private function pluginRows(string $table, string $pluginId): array
    {
        return $this->rows(
            sprintf('SELECT * FROM `%s%s` WHERE plugin_id = :plugin_id', $this->prefix, $table),
            ['plugin_id' => $pluginId],
        );
    }

    /** @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    private function rowsForIds(string $table, string $column, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        return $this->rows(sprintf(
            'SELECT * FROM `%s` WHERE `%s` IN (%s)',
            $table,
            $column,
            $placeholders,
        ), $ids);
    }

    /** @param list<int> $relatedIds
     * @return list<array<string, mixed>>
     */
    private function relatedRows(
        string $table,
        string $pluginColumn,
        string $pluginId,
        string $relatedColumn,
        array $relatedIds,
    ): array {
        $sql = sprintf(
            'SELECT * FROM `%s%s` WHERE `%s` = ?',
            $this->prefix,
            $table,
            $pluginColumn,
        );
        $parameters = [$pluginId];
        if ($relatedIds !== []) {
            $sql .= sprintf(
                ' OR `%s` IN (%s)',
                $relatedColumn,
                implode(', ', array_fill(0, count($relatedIds), '?')),
            );
            $parameters = [...$parameters, ...$relatedIds];
        }
        return $this->rows($sql, $parameters);
    }

    /** @param list<int> $relatedIds */
    private function deleteRelatedRows(
        string $table,
        string $pluginColumn,
        string $pluginId,
        string $relatedColumn,
        array $relatedIds,
    ): void {
        $sql = sprintf(
            'DELETE FROM `%s%s` WHERE `%s` = ?',
            $this->prefix,
            $table,
            $pluginColumn,
        );
        $parameters = [$pluginId];
        if ($relatedIds !== []) {
            $sql .= sprintf(
                ' OR `%s` IN (%s)',
                $relatedColumn,
                implode(', ', array_fill(0, count($relatedIds), '?')),
            );
            $parameters = [...$parameters, ...$relatedIds];
        }
        $this->execute($sql, $parameters);
    }

    /** @param array<int|string, int|string> $parameters
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $parameters): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /** @param array<int|string, int|string> $parameters */
    private function execute(string $sql, array $parameters): void
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
    }
}
