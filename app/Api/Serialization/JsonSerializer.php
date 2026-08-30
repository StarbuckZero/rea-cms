<?php

declare(strict_types=1);

namespace ReaCms\Api\Serialization;

use ReaCms\Core\Http\Response;

final class JsonSerializer implements Serializer
{
    public function serialize(array $document): Response
    {
        return Response::json($document);
    }
}
