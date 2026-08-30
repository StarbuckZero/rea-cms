<?php

declare(strict_types=1);

namespace ReaCms\Operations;

interface BackupDatabase
{
    /** @param list<string> $tables
     * @return array<string, list<array<string, mixed>>>
     */
    public function export(array $tables): array;

    public function isEmpty(): bool;

    /** @param array<string, list<array<string, mixed>>> $tables */
    public function restore(array $tables): void;
}
