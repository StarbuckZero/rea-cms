<?php

declare(strict_types=1);

namespace ReaCms\Setup;

final readonly class PlatformRequirement
{
    public function __construct(
        public string $label,
        public bool $passed,
        public string $detail,
        public bool $required = true,
    ) {
    }
}
