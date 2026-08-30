<?php

declare(strict_types=1);

namespace ReaCms\Api\Serialization;

final class SerializerRegistry
{
    /** @var array<string, Serializer> */
    private array $serializers;

    public function __construct()
    {
        $this->serializers = [
            'json' => new JsonSerializer(),
            'html' => new HtmlSerializer(),
            'txt' => new TextSerializer(),
        ];
    }

    public function get(string $format): ?Serializer
    {
        return $this->serializers[$format] ?? null;
    }
}
