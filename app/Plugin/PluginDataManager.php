<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

interface PluginDataManager
{
    public function summarize(PluginRecord $plugin): PluginDataSummary;

    public function export(PluginRecord $plugin): string;

    public function purge(PluginRecord $plugin): void;
}
