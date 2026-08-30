<?php

declare(strict_types=1);

namespace ReaCms\Api\Serialization;

use ReaCms\Core\Http\Response;

final class TextSerializer implements Serializer
{
    public function serialize(array $document): Response
    {
        $body = (string) json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return new Response($body . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
