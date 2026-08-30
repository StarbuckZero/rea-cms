<?php

declare(strict_types=1);

namespace ReaCms\Api\Policy;

final class PolicyDecision
{
    public function __construct(public readonly bool $allowed, public readonly string $reason = '')
    {
    }
}
