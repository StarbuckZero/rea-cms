<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

final class StagedPackage
{
    public function __construct(
        public readonly Manifest $manifest,
        public readonly string $directory,
        public readonly string $packageHash,
    ) {
    }
}
