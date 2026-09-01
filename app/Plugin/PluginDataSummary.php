<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

final class PluginDataSummary
{
    /** @param array<string, int> $tableRows */
    public function __construct(public readonly array $tableRows)
    {
    }

    public function totalRows(): int
    {
        return array_sum($this->tableRows);
    }

    public function hasData(): bool
    {
        return $this->totalRows() > 0;
    }
}
