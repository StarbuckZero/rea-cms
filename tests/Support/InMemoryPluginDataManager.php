<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use ReaCms\Plugin\PluginDataManager;
use ReaCms\Plugin\PluginDataSummary;
use ReaCms\Plugin\PluginRecord;

final class InMemoryPluginDataManager implements PluginDataManager
{
    /** @var array<string, int> */
    public array $rows = [];
    public int $exports = 0;
    public int $purges = 0;

    public function __construct(private readonly string $root)
    {
    }

    public function summarize(PluginRecord $plugin): PluginDataSummary
    {
        return new PluginDataSummary($this->rows);
    }

    public function export(PluginRecord $plugin): string
    {
        $this->exports++;
        $path = $this->root . '/backup-' . $this->exports . '.json';
        file_put_contents($path, '{}');
        return $path;
    }

    public function purge(PluginRecord $plugin): void
    {
        $this->purges++;
    }
}
