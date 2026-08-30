<?php

declare(strict_types=1);

namespace ReaCms\Content;

final class ResourceDefinition
{
    /**
     * @param array<string, string> $fields
     * @param list<string> $required
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly string $pluginId,
        public readonly string $resource,
        public readonly string $table,
        public readonly array $fields,
        public readonly array $required,
        public readonly array $permissions,
    ) {
        if (
            preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $pluginId) !== 1
            || preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $resource) !== 1
            || preg_match('/^plugin_' . preg_quote($pluginId, '/') . '_[a-z][a-z0-9_]{0,47}$/D', $table) !== 1
        ) {
            throw new ContentException('The resource definition escapes its plugin namespace.');
        }
        foreach ($fields as $field => $type) {
            if (
                preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $field) !== 1
                || !in_array($type, ['string', 'text', 'integer', 'boolean', 'datetime', 'json', 'media'], true)
            ) {
                throw new ContentException('The resource definition contains an invalid field.');
            }
        }
        if (array_diff($required, array_keys($fields)) !== []) {
            throw new ContentException('A required field is not declared.');
        }
        foreach ($permissions as $permission) {
            if (!str_starts_with($permission, $pluginId . '.')) {
                throw new ContentException('A resource permission escapes its plugin namespace.');
            }
        }
    }
}
