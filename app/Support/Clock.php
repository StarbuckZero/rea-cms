<?php

declare(strict_types=1);

namespace ReaCms\Support;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
