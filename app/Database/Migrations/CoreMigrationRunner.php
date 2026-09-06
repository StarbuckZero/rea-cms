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
        $plan = $this->plan();
        $migrated = [];

        foreach ($this->migrationFiles() as $file) {
            $version = basename($file, '.sql');
            if (!in_array($version, $plan->pending, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException(sprintf('Migration "%s" is empty or unreadable.', $version));
            }

            $checksum = hash('sha256', $sql);
            $expandedSql = str_replace('{{prefix}}', $this->tablePrefix, $sql);
            $this->database->execute($expandedSql);
            $this->database->record($this->trackingTable, $version, $checksum);
            $migrated[] = $version;
        }

        return $migrated;
    }

    public function plan(): CoreMigrationPlan
    {
        $this->database->ensureTrackingTable($this->trackingTable);
        $recorded = $this->database->appliedMigrations($this->trackingTable);
        $available = [];

        foreach ($this->migrationFiles() as $file) {
            $version = basename($file, '.sql');
            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException(sprintf('Migration "%s" is empty or unreadable.', $version));
            }

            $checksum = hash('sha256', $sql);
            if (isset($recorded[$version]) && !hash_equals($recorded[$version], $checksum)) {
                throw new RuntimeException(sprintf(
                    'Applied migration "%s" no longer matches its recorded checksum.',
                    $version,
                ));
            }

            $available[$version] = $checksum;
        }

        $unknown = array_values(array_diff(array_keys($recorded), array_keys($available)));
        if ($unknown !== []) {
            throw new RuntimeException(sprintf(
                'The database contains migration "%s", which is not present in this release.',
                $unknown[0],
            ));
        }

        $applied = array_keys($recorded);
        sort($applied, SORT_STRING);
        $pending = array_values(array_diff(array_keys($available), $applied));

        return new CoreMigrationPlan($applied, $pending);
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
