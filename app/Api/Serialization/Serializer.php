<?php

declare(strict_types=1);

namespace ReaCms\Api\Serialization;

use ReaCms\Core\Http\Response;

interface Serializer
{
    /** @param array<string, mixed> $document */
    public function serialize(array $document): Response;
}
