<?php

declare(strict_types=1);

namespace ReaCms\Content;

use JsonException;

final class ContentImporter
{
    public function __construct(private readonly ContentValidator $validator = new ContentValidator())
    {
    }

    /**
     * @param callable(array<string, mixed>): void $persist
     * @return array{valid: int, persisted: int}
     */
    public function import(ResourceDefinition $definition, string $json, bool $validationOnly, callable $persist): array
    {
        try {
            $document = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ContentException('The import is not valid JSON.', previous: $exception);
        }
        if (!is_array($document) || !array_is_list($document)) {
            throw new ContentException('The import must contain a list of records.');
        }
        $validated = [];
        foreach ($document as $record) {
            if (!is_array($record) || array_is_list($record)) {
                throw new ContentException('An imported record is invalid.');
            }
            $validated[] = $this->validator->validate($definition, $record);
        }
        if (!$validationOnly) {
            foreach ($validated as $record) {
                $persist($record);
            }
        }
        return ['valid' => count($validated), 'persisted' => $validationOnly ? 0 : count($validated)];
    }
}
