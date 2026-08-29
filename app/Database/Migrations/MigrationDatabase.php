<?php

declare(strict_types=1);

namespace ReaCms\Database\Migrations;

interface MigrationDatabase
{
    public function ensureTrackingTable(string $table): void;

    /**
     * @return array<string, string> Migration version to checksum.
     */
    public function appliedMigrations(string $table): array;

    public function execute(string $sql): void;

    public function record(string $table, string $version, string $checksum): void;
}
