<?php

declare(strict_types=1);

namespace ReaCms\Api\Template;

use PDO;
use ReaCms\Plugin\PluginException;
use RuntimeException;

final class PdoPluginApiTemplateRepository implements PluginApiTemplateRepository
{
    private const SLOTS = ['html_list', 'html_detail', 'txt_list', 'txt_detail'];

    private readonly string $settingsTable;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $pluginRoot,
        string $prefix = 'rea_',
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }
        $this->settingsTable = $prefix . 'settings';
    }

    public function all(string $pluginId): array
    {
        $this->assertPluginId($pluginId);
        $templates = [];
        foreach (self::SLOTS as $slot) {
            [$format, $mode] = explode('_', $slot, 2);
            $templates[$slot] = $this->template($pluginId, $format, $mode);
        }

        /** @var array{html_list:string,html_detail:string,txt_list:string,txt_detail:string} $templates */
        return $templates;
    }

    public function defaults(string $pluginId): array
    {
        $this->assertPluginId($pluginId);
        $templates = [];
        foreach (self::SLOTS as $slot) {
            [$format, $mode] = explode('_', $slot, 2);
            $templates[$slot] = $this->defaultTemplate($pluginId, $format, $mode, $slot);
        }

        /** @var array{html_list:string,html_detail:string,txt_list:string,txt_detail:string} $templates */
        return $templates;
    }

    public function template(string $pluginId, string $format, string $mode): string
    {
        $slot = $this->slot($format, $mode);
        $this->assertPluginId($pluginId);
        $statement = $this->pdo->prepare(sprintf(
            'SELECT setting_value FROM `%s` WHERE setting_key=:setting_key LIMIT 1',
            $this->settingsTable,
        ));
        $statement->execute(['setting_key' => $this->key($pluginId, $slot)]);
        $value = $statement->fetchColumn();
        if (is_string($value)) {
            return $value;
        }

        return $this->defaultTemplate($pluginId, $format, $mode, $slot);
    }

    public function save(string $pluginId, array $templates): void
    {
        $this->assertPluginId($pluginId);
        foreach (self::SLOTS as $slot) {
            if (!array_key_exists($slot, $templates) || !is_string($templates[$slot])) {
                throw new PluginException('All four API templates are required.');
            }
        }

        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s` (setting_key, setting_value, is_public) VALUES (:setting_key, :setting_value, 0) '
                . 'ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), is_public=0',
            $this->settingsTable,
        ));
        $this->pdo->beginTransaction();
        try {
            foreach (self::SLOTS as $slot) {
                $statement->execute([
                    'setting_key' => $this->key($pluginId, $slot),
                    'setting_value' => $templates[$slot],
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function reset(string $pluginId): void
    {
        $this->assertPluginId($pluginId);
        $keys = [];
        foreach (self::SLOTS as $index => $slot) {
            $keys['key_' . $index] = $this->key($pluginId, $slot);
        }
        $placeholders = implode(', ', array_map(static fn (string $key): string => ':' . $key, array_keys($keys)));
        $statement = $this->pdo->prepare(sprintf(
            'DELETE FROM `%s` WHERE setting_key IN (%s)',
            $this->settingsTable,
            $placeholders,
        ));
        $statement->execute($keys);
    }

    private function slot(string $format, string $mode): string
    {
        $slot = $format . '_' . $mode;
        if (!in_array($slot, self::SLOTS, true)) {
            throw new PluginException('The requested plugin API template is invalid.');
        }

        return $slot;
    }

    private function assertPluginId(string $pluginId): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $pluginId) !== 1) {
            throw new PluginException('The plugin ID is invalid.');
        }
    }

    private function key(string $pluginId, string $slot): string
    {
        return 'plugin_api_template.' . $pluginId . '.' . $slot;
    }

    private function defaultTemplate(string $pluginId, string $format, string $mode, string $slot): string
    {
        $path = sprintf('%s/%s/templates/api/%s.%s', rtrim($this->pluginRoot, '/'), $pluginId, $mode, $format);
        $default = is_file($path) ? file_get_contents($path) : false;
        if (!is_string($default)) {
            throw new PluginException(sprintf('The %s API template for plugin "%s" is unavailable.', $slot, $pluginId));
        }

        return $default;
    }
}
