<?php

declare(strict_types=1);

namespace ReaCms\Database\Migrations;

use PDO;
use RuntimeException;

final class PdoMigrationDatabase implements MigrationDatabase
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ensureTrackingTable(string $table): void
    {
        $this->pdo->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` ('
                . '`version` VARCHAR(191) NOT NULL PRIMARY KEY,'
                . '`checksum` CHAR(64) NOT NULL,'
                . '`applied_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            $table,
        ));
    }

    public function appliedMigrations(string $table): array
    {
        $statement = $this->pdo->query(sprintf(
            'SELECT `version`, `checksum` FROM `%s` ORDER BY `version`',
            $table,
        ));

        if ($statement === false) {
            throw new RuntimeException('Applied migration versions could not be read.');
        }

        $migrations = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (is_string($row['version'] ?? null) && is_string($row['checksum'] ?? null)) {
                $migrations[$row['version']] = $row['checksum'];
            }
        }

        return $migrations;
    }

    public function execute(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    public function record(string $table, string $version, string $checksum): void
    {
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s` (`version`, `checksum`) VALUES (:version, :checksum)',
            $table,
        ));
        $statement->execute([
            'version' => $version,
            'checksum' => $checksum,
        ]);
    }
}
