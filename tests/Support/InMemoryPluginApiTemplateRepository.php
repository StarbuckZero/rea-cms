<?php

declare(strict_types=1);

namespace ReaCms\Tests\Support;

use ReaCms\Api\Template\PluginApiTemplateRepository;

final class InMemoryPluginApiTemplateRepository implements PluginApiTemplateRepository
{
    /** @var array<string, array{html_list:string,html_detail:string,txt_list:string,txt_detail:string}> */
    public array $templates = [];

    public function all(string $pluginId): array
    {
        return $this->templates[$pluginId] ?? $this->defaults($pluginId);
    }

    public function defaults(string $pluginId): array
    {
        return [
            'html_list' => '<p>{' . $pluginId . '.title}</p>',
            'html_detail' => '<h1>{' . $pluginId . '.title}</h1>{' . $pluginId . '.content | sanitized_html}',
            'txt_list' => '{' . $pluginId . '.title}',
            'txt_detail' => '{' . $pluginId . '.title}' . "\n" . '{' . $pluginId . '.content}',
        ];
    }

    public function template(string $pluginId, string $format, string $mode): string
    {
        return $this->all($pluginId)[$format . '_' . $mode];
    }

    public function save(string $pluginId, array $templates): void
    {
        $this->templates[$pluginId] = $templates;
    }

    public function reset(string $pluginId): void
    {
        unset($this->templates[$pluginId]);
    }
}
