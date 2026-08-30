<?php

declare(strict_types=1);

namespace ReaCms\Api\Policy;

interface PolicyRepository
{
    public function forResource(string $resource, string $operation): PolicySet;
}
