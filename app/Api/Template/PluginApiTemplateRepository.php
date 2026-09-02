<?php

declare(strict_types=1);

namespace ReaCms\Api\Template;

interface PluginApiTemplateRepository
{
    /** @return array{html_list:string,html_detail:string,txt_list:string,txt_detail:string} */
    public function all(string $pluginId): array;

    /** @return array{html_list:string,html_detail:string,txt_list:string,txt_detail:string} */
    public function defaults(string $pluginId): array;

    public function template(string $pluginId, string $format, string $mode): string;

    /** @param array{html_list:string,html_detail:string,txt_list:string,txt_detail:string} $templates */
    public function save(string $pluginId, array $templates): void;

    public function reset(string $pluginId): void;
}
