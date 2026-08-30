<?php

declare(strict_types=1);

namespace ReaCms\Operations;

final class PerformanceBudget
{
    public function __construct(
        private readonly int $maximumQueries = 25,
        private readonly int $maximumAssetBytes = 250_000,
    ) {
    }

    public function verify(int $queries, int $assetBytes): void
    {
        if ($queries > $this->maximumQueries || $assetBytes > $this->maximumAssetBytes) {
            throw new \RuntimeException('The measured request exceeds its performance budget.');
        }
    }
}
