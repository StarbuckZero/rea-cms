<?php

declare(strict_types=1);

namespace ReaCms\Content;

final class ContentValidator
{
    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validate(ResourceDefinition $definition, array $input): array
    {
        $unknown = array_diff(array_keys($input), array_keys($definition->fields));
        if ($unknown !== []) {
            throw new ContentException('Unknown content fields are not accepted.');
        }
        foreach ($definition->required as $field) {
            if (!array_key_exists($field, $input) || $input[$field] === '') {
                throw new ContentException('A required content field is missing.');
            }
        }
        foreach ($input as $field => $value) {
            $valid = match ($definition->fields[$field]) {
                'string', 'text', 'datetime' => is_string($value),
                'integer', 'media' => is_int($value) && $value > 0,
                'boolean' => is_bool($value),
                'json' => is_array($value),
                default => false,
            };
            if (!$valid) {
                throw new ContentException('A content field has the wrong type.');
            }
        }
        return $input;
    }
}
