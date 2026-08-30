<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

final class PluginRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly string $state,
        public readonly string $packageHash,
    ) {
    }
}
