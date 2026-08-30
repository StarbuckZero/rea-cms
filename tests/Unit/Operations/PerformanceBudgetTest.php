<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;
use ReaCms\Operations\PerformanceBudget;

final class PerformanceBudgetTest extends TestCase
{
    public function testBudgetAcceptsBaselineAndRejectsRegressions(): void
    {
        $budget = new PerformanceBudget(10, 1000);
        $budget->verify(10, 1000);

        $this->expectException(\RuntimeException::class);
        $budget->verify(11, 1000);
    }
}
