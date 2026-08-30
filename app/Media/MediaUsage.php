<?php

declare(strict_types=1);

namespace ReaCms\Media;

interface MediaUsage
{
    public function count(int $mediaId): int;
}
