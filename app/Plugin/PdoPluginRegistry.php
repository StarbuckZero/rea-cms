<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use JsonException;
use PDO;
use PDOStatement;
use RuntimeException;

final class PdoPluginRegistry implements PluginRegistry
{
    private readonly string $table;

    public function __construct(private readonly PDO $pdo, string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }
        $this->table = $prefix . 'plugins';
    }

    public function find(string $pluginId): ?PluginRecord
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT plugin_id, name, version, state, package_hash, manifest_json '
            . 'FROM `%s` WHERE plugin_id = :plugin_id LIMIT 1',
            $this->table,
        ));
        $statement->execute(['plugin_id' => $pluginId]);
        $row = $statement->fetch();
        return is_array($row) ? $this->record($row) : null;
    }

    /** @return list<PluginRecord> */
    public function active(): array
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT plugin_id, name, version, state, package_hash, manifest_json '
            . 'FROM `%s` WHERE state = \'enabled\' ORDER BY name, plugin_id',
            $this->table,
        ));
        $statement->execute();

        $records = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $records[] = $this->record($row);
            }
        }

        return $records;
    }

    /** @return list<PluginRecord> */
    public function all(): array
    {
        $statement = $this->pdo->query(sprintf(
            'SELECT plugin_id, name, version, state, package_hash, manifest_json FROM `%s` ORDER BY name, plugin_id',
            $this->table,
        ));
        if (!$statement instanceof PDOStatement) {
            throw new PluginException('The installed plugin list could not be read.');
        }
        $records = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $records[] = $this->record($row);
            }
        }
        return $records;
    }

    public function install(StagedPackage $package): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s` '
            . '(plugin_id, name, version, state, manifest_hash, package_hash, manifest_json) '
            . 'VALUES (:id, :name, :version, :state, :manifest_hash, :package_hash, :manifest_json)',
            $this->table,
        ));
        $statement->execute($this->values($package, 'disabled'));
    }

    public function update(StagedPackage $package): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET name = :name, version = :version, manifest_hash = :manifest_hash, '
            . 'package_hash = :package_hash, manifest_json = :manifest_json WHERE plugin_id = :id',
            $this->table,
        ));
        $values = $this->values($package, 'disabled');
        unset($values['state']);
        $statement->execute($values);
    }

    public function setState(string $pluginId, string $state): void
    {
        if (!in_array($state, ['disabled', 'enabled', 'maintenance', 'uninstalled'], true)) {
            throw new PluginException('The requested plugin state is invalid.');
        }
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET state = :state WHERE plugin_id = :plugin_id',
            $this->table,
        ));
        $statement->execute(['state' => $state, 'plugin_id' => $pluginId]);
    }

    public function remove(string $pluginId): void
    {
        $statement = $this->pdo->prepare(sprintf('DELETE FROM `%s` WHERE plugin_id = :plugin_id', $this->table));
        $statement->execute(['plugin_id' => $pluginId]);
    }

    /** @return array<string, string> */
    private function values(StagedPackage $package, string $state): array
    {
        try {
            $json = json_encode($package->manifest->document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new PluginException('The validated manifest could not be stored.', previous: $exception);
        }
        return [
            'id' => $package->manifest->id,
            'name' => $package->manifest->name,
            'version' => $package->manifest->version,
            'state' => $state,
            'manifest_hash' => $package->manifest->hash,
            'package_hash' => $package->packageHash,
            'manifest_json' => $json,
        ];
    }

    /** @param array<string, mixed> $row */
    private function record(array $row): PluginRecord
    {
        try {
            $manifest = json_decode((string) $row['manifest_json'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $manifest = [];
        }

        return new PluginRecord(
            (string) $row['plugin_id'],
            (string) $row['version'],
            (string) $row['state'],
            (string) $row['package_hash'],
            (string) $row['name'],
            is_array($manifest) && is_string($manifest['description'] ?? null)
                ? $manifest['description']
                : '',
            is_array($manifest) && is_string($manifest['navigation']['label'] ?? null)
                ? $manifest['navigation']['label']
                : null,
            is_array($manifest) && is_string($manifest['navigation']['path'] ?? null)
                ? $manifest['navigation']['path']
                : null,
            is_array($manifest) && is_string($manifest['author'] ?? null) ? $manifest['author'] : '',
            is_array($manifest) && is_array($manifest['tables'] ?? null)
                ? array_values(array_filter($manifest['tables'], 'is_string'))
                : [],
            is_array($manifest) ? $manifest : [],
        );
    }
}
