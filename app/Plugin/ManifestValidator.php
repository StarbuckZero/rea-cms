<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use JsonException;

final class ManifestValidator
{
    private const KEYS = [
        'schemaVersion', 'id', 'name', 'version', 'reaCmsVersion', 'description',
        'tables', 'permissions', 'api', 'navigation',
    ];

    public function __construct(private readonly string $cmsVersion = '1.0.0')
    {
    }

    public function validate(string $json): Manifest
    {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PluginException('plugin.json is not valid JSON.', previous: $exception);
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new PluginException('plugin.json must contain an object.');
        }
        foreach (array_keys($data) as $key) {
            if (!is_string($key) || !in_array($key, self::KEYS, true)) {
                throw new PluginException('plugin.json contains an unknown capability.');
            }
        }

        $id = $this->requiredString($data, 'id', 32);
        $name = $this->requiredString($data, 'name', 191);
        $version = $this->requiredString($data, 'version', 32);
        $compatibility = $this->requiredString($data, 'reaCmsVersion', 32);
        $description = $this->requiredString($data, 'description', 1000, true);
        if (($data['schemaVersion'] ?? null) !== 1 || preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $id) !== 1) {
            throw new PluginException('The manifest schema version or plugin ID is invalid.');
        }
        if (preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[0-9A-Za-z.-]+)?$/D', $version) !== 1) {
            throw new PluginException('The plugin version must be semantic.');
        }
        if (
            preg_match('/^\^([0-9]+)\.([0-9]+)$/D', $compatibility, $matches) !== 1
            || (int) $matches[1] !== (int) explode('.', $this->cmsVersion)[0]
        ) {
            throw new PluginException('The plugin is not compatible with this Rea CMS version.');
        }

        $tables = $this->stringList($data, 'tables');
        foreach ($tables as $table) {
            if (preg_match('/^plugin_' . preg_quote($id, '/') . '_[a-z][a-z0-9_]{0,47}$/D', $table) !== 1) {
                throw new PluginException('A declared table escapes the plugin namespace.');
            }
        }
        $permissions = $this->stringList($data, 'permissions');
        foreach ($permissions as $permission) {
            if (preg_match('/^' . preg_quote($id, '/') . '\.[a-z][a-z0-9_.-]{1,190}$/D', $permission) !== 1) {
                throw new PluginException('A permission escapes the plugin namespace.');
            }
        }
        if (isset($data['navigation'])) {
            $navigation = $data['navigation'];
            if (
                !is_array($navigation) || array_is_list($navigation)
                || array_diff(array_keys($navigation), ['label', 'path']) !== []
                || !is_string($navigation['label'] ?? null)
                || !is_string($navigation['path'] ?? null)
                || preg_match('#^/cms/[a-z][a-z0-9_-]{1,31}$#D', $navigation['path']) !== 1
            ) {
                throw new PluginException('The plugin navigation metadata is invalid.');
            }
        }

        return new Manifest(
            $id,
            $name,
            $version,
            $compatibility,
            $description,
            $tables,
            $permissions,
            $data,
            hash('sha256', $json),
        );
    }

    /** @param array<string, mixed> $data */
    private function requiredString(array $data, string $key, int $maximum, bool $emptyAllowed = false): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || (!$emptyAllowed && trim($value) === '') || strlen($value) > $maximum) {
            throw new PluginException('The manifest field ' . $key . ' is invalid.');
        }
        return $value;
    }

    /** @param array<string, mixed> $data
     * @return list<string>
     */
    private function stringList(array $data, string $key): array
    {
        $values = $data[$key] ?? null;
        if (!is_array($values) || !array_is_list($values)) {
            throw new PluginException('The manifest field ' . $key . ' must be a list.');
        }
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw new PluginException('The manifest field ' . $key . ' must contain strings.');
            }
        }
        if (count(array_unique($values)) !== count($values)) {
            throw new PluginException('The manifest field ' . $key . ' contains duplicates.');
        }
        return $values;
    }
}
