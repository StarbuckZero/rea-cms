<?php

declare(strict_types=1);

namespace ReaCms\Database\Migrations;

final readonly class CoreMigrationPlan
{
    /**
     * @param list<string> $applied
     * @param list<string> $pending
     */
    public function __construct(
        public array $applied,
        public array $pending,
    ) {
    }

    public function isCurrent(): bool
    {
        return $this->pending === [];
    }
}
