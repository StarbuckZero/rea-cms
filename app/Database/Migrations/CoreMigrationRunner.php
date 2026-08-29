<?php

declare(strict_types=1);

namespace ReaCms\Database\Migrations;

use RuntimeException;

final class CoreMigrationRunner
{
    private readonly string $trackingTable;

    public function __construct(
        private readonly MigrationDatabase $database,
        private readonly string $migrationPath,
        string $tablePrefix = 'rea_',
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $tablePrefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }

        $this->trackingTable = $tablePrefix . 'migrations';
        $this->tablePrefix = $tablePrefix;
    }

    private readonly string $tablePrefix;

    /**
     * @return list<string> Applied migration versions.
     */
    public function migrate(): array
    {
        $this->database->ensureTrackingTable($this->trackingTable);
        $applied = $this->database->appliedMigrations($this->trackingTable);
        $migrated = [];

        foreach ($this->migrationFiles() as $file) {
            $version = basename($file, '.sql');
            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException(sprintf('Migration "%s" is empty or unreadable.', $version));
            }

            $checksum = hash('sha256', $sql);
            if (isset($applied[$version])) {
                if (!hash_equals($applied[$version], $checksum)) {
                    throw new RuntimeException(sprintf(
                        'Applied migration "%s" no longer matches its recorded checksum.',
                        $version,
                    ));
                }

                continue;
            }

            $expandedSql = str_replace('{{prefix}}', $this->tablePrefix, $sql);
            $this->database->execute($expandedSql);
            $this->database->record($this->trackingTable, $version, $checksum);
            $migrated[] = $version;
        }

        return $migrated;
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(): array
    {
        $files = glob(rtrim($this->migrationPath, DIRECTORY_SEPARATOR) . '/*.sql');

        if ($files === false) {
            throw new RuntimeException('The migration directory could not be read.');
        }

        $files = array_values(array_filter($files, static fn (string $file): bool => (
            preg_match('/^[0-9]{3}_[a-z0-9_]+\.sql$/', basename($file)) === 1
        )));
        sort($files, SORT_STRING);

        return $files;
    }
}
