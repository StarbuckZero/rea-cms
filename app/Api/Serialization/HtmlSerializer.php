<?php

declare(strict_types=1);

namespace ReaCms\Api\Serialization;

use ReaCms\Core\Http\Response;

final class HtmlSerializer implements Serializer
{
    public function serialize(array $document): Response
    {
        $json = (string) json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $escaped = htmlspecialchars($json, ENT_QUOTES, 'UTF-8');

        return Response::html(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Rea CMS API</title></head>'
            . '<body><main><h1>Rea CMS API</h1><pre>'
            . $escaped
            . '</pre></main></body></html>',
        );
    }
}
