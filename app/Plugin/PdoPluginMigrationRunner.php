<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use PDO;
use RuntimeException;

final class PdoPluginMigrationRunner
{
    private readonly string $table;

    public function __construct(
        private readonly PDO $pdo,
        private readonly DeclarativeMigration $compiler = new DeclarativeMigration(),
        string $prefix = 'rea_',
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }
        $this->table = $prefix . 'plugin_migrations';
    }

    public function apply(StagedPackage $package): void
    {
        $files = glob($package->directory . '/migrations/*.json') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $name = basename($file);
            if (preg_match('/^[0-9]{3}_[a-z0-9_]+\.json$/D', $name) !== 1) {
                throw new PluginException('Plugin migration filenames must be ordered and normalized.');
            }
            $json = file_get_contents($file);
            if (!is_string($json)) {
                throw new PluginException('A plugin migration could not be read.');
            }
            $checksum = hash('sha256', $json);
            $existing = $this->checksum($package->manifest->id, $name);
            if ($existing !== null) {
                if (!hash_equals($existing, $checksum)) {
                    throw new PluginException('An applied plugin migration checksum changed.');
                }
                continue;
            }
            foreach ($this->compiler->compile($package->manifest->id, $package->manifest, $json) as $sql) {
                $this->pdo->exec($sql);
            }
            $statement = $this->pdo->prepare(sprintf(
                'INSERT INTO `%s` (plugin_id, migration, checksum) VALUES (:plugin_id, :migration, :checksum)',
                $this->table,
            ));
            $statement->execute([
                'plugin_id' => $package->manifest->id,
                'migration' => $name,
                'checksum' => $checksum,
            ]);
        }
    }

    private function checksum(string $pluginId, string $migration): ?string
    {
        $statement = $this->pdo->prepare(sprintf(
            'SELECT checksum FROM `%s` WHERE plugin_id = :plugin_id AND migration = :migration LIMIT 1',
            $this->table,
        ));
        $statement->execute(['plugin_id' => $pluginId, 'migration' => $migration]);
        $checksum = $statement->fetchColumn();
        return is_string($checksum) ? $checksum : null;
    }
}
