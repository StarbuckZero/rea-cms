<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use ReaCms\Database\Migrations\MigrationDatabase;

final class InMemoryMigrationDatabase implements MigrationDatabase
{
    public string $trackingTable = '';

    /** @var list<string> */
    public array $statements = [];

    /** @var list<array{version: string, checksum: string}> */
    public array $records = [];

    /**
     * @param array<string, string> $applied
     */
    public function __construct(private readonly array $applied = [])
    {
    }

    public function ensureTrackingTable(string $table): void
    {
        $this->trackingTable = $table;
    }

    public function appliedMigrations(string $table): array
    {
        return $this->applied;
    }

    public function execute(string $sql): void
    {
        $this->statements[] = $sql;
    }

    public function record(string $table, string $version, string $checksum): void
    {
        $this->records[] = [
            'version' => $version,
            'checksum' => $checksum,
        ];
    }
}
