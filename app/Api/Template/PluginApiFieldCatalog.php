<?php

declare(strict_types=1);

namespace ReaCms\Api\Template;

use ReaCms\Plugin\PluginRecord;

final class PluginApiFieldCatalog
{
    public function __construct(private readonly string $pluginRoot)
    {
    }

    /** @return list<array{path:string,binding:string,label:string,description:string,type:string}> */
    public function fields(PluginRecord $plugin): array
    {
        $document = $this->pluginDocument($plugin);
        $candidate = $document['api']['binding'] ?? $document['api']['resource'] ?? null;
        $resource = is_string($candidate)
            && preg_match('/^[a-z][a-zA-Z0-9_-]{1,31}$/D', $candidate) === 1
                ? $candidate
                : $plugin->id;
        $metadata = $document['api']['fields'] ?? null;
        $fields = is_array($metadata) && !array_is_list($metadata)
            ? $this->metadataFields($metadata, $resource)
            : [];

        return $fields !== [] ? $fields : $this->schemaFields($plugin->id, $resource);
    }

    /** @return array<string, mixed> */
    public function sample(PluginRecord $plugin): array
    {
        $sample = [];
        foreach ($this->fields($plugin) as $field) {
            $value = match ($field['type']) {
                'integer' => 123,
                'number' => 12.5,
                'boolean' => true,
                'datetime' => '2026-01-15T12:30:00+00:00',
                'url' => 'https://example.com/' . str_replace('.', '/', $field['path']),
                'html' => '<p>Sample ' . htmlspecialchars(
                    strtolower($field['label']),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8',
                ) . '.</p>',
                default => 'Sample ' . strtolower($field['label']),
            };
            $this->setPath($sample, $field['path'], $value);
        }

        return $sample;
    }

    /** @return array<string, mixed> */
    private function pluginDocument(PluginRecord $plugin): array
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $plugin->id) === 1) {
            $path = rtrim($this->pluginRoot, '/') . '/' . $plugin->id . '/plugin.json';
            $json = is_file($path) ? file_get_contents($path) : false;
            if (is_string($json)) {
                $document = json_decode($json, true);
                if (is_array($document) && !array_is_list($document)) {
                    return $document;
                }
            }
        }

        return $plugin->manifest;
    }

    /**
     * @param array<mixed> $metadata
     * @return list<array{path:string,binding:string,label:string,description:string,type:string}>
     */
    private function metadataFields(array $metadata, string $resource): array
    {
        $fields = [];
        foreach ($metadata as $path => $details) {
            if (
                !is_string($path) || !$this->validPath($path)
                || !is_array($details) || array_is_list($details)
                || !is_string($details['type'] ?? null)
            ) {
                continue;
            }
            $label = is_string($details['label'] ?? null) && trim($details['label']) !== ''
                ? $details['label']
                : $this->label($path);
            $fields[] = [
                'path' => $path,
                'binding' => '{' . $resource . '.' . $path . '}',
                'label' => $label,
                'description' => is_string($details['description'] ?? null) ? $details['description'] : '',
                'type' => $details['type'],
            ];
        }

        return $fields;
    }

    /** @return list<array{path:string,binding:string,label:string,description:string,type:string}> */
    private function schemaFields(string $pluginId, string $resource): array
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $pluginId) !== 1) {
            return [];
        }
        $path = rtrim($this->pluginRoot, '/') . '/' . $pluginId . '/schema/fields.json';
        $json = is_file($path) ? file_get_contents($path) : false;
        $schema = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($schema)) {
            return [];
        }

        $definitions = [];
        if (is_array($schema['fields'] ?? null)) {
            $definitions[] = $schema['fields'];
        }
        if (is_array($schema['resources'] ?? null)) {
            foreach ($schema['resources'] as $definition) {
                if (is_array($definition) && is_array($definition['fields'] ?? null)) {
                    $definitions[] = $definition['fields'];
                }
            }
        }

        $metadata = [];
        foreach ($definitions as $definition) {
            foreach ($definition as $field => $type) {
                if (is_string($field) && is_string($type) && $this->validPath($field)) {
                    $metadata[$field] = ['type' => $this->fieldType($type)];
                }
            }
        }

        return $this->metadataFields($metadata, $resource);
    }

    private function validPath(string $path): bool
    {
        return strlen($path) <= 191
            && substr_count($path, '.') <= 7
            && preg_match('/^[a-z][a-zA-Z0-9_]*(?:\.[a-z][a-zA-Z0-9_]*)*$/D', $path) === 1;
    }

    private function label(string $path): string
    {
        $label = str_replace(['.', '_'], ' ', $path);
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $label) ?? $label;

        return ucfirst($label);
    }

    private function fieldType(string $type): string
    {
        return match ($type) {
            'integer', 'media' => 'integer',
            'decimal', 'float', 'number' => 'number',
            'boolean' => 'boolean',
            'datetime', 'date' => 'datetime',
            'url' => 'url',
            default => in_array($type, ['text', 'html'], true) ? $type : 'string',
        };
    }

    /** @param array<string, mixed> $data */
    private function setPath(array &$data, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $cursor = &$data;
        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $cursor[$segment] = $value;
                break;
            }
            if (!is_array($cursor[$segment] ?? null)) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
        unset($cursor);
    }
}
