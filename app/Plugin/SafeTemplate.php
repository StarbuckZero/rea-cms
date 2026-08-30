<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

final class SafeTemplate
{
    /** @param array<string, mixed> $context */
    public function render(string $template, array $context): string
    {
        if (str_contains($template, '<?') || str_contains($template, '{%')) {
            throw new PluginException('The template contains executable syntax.');
        }

        $rendered = preg_replace_callback(
            '/\{\{\s*([a-z][a-z0-9_.]*)\s*(?:\|\s*(sanitized_html))?\s*\}\}/i',
            function (array $matches) use ($context): string {
                $value = $this->value($context, $matches[1]);
                if (($matches[2] ?? '') === 'sanitized_html') {
                    return $value instanceof SafeHtml ? $value->value : SafeHtml::sanitize((string) $value)->value;
                }
                return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            },
            $template,
        );
        if ($rendered === null || preg_match('/\{\{|\}\}/', $rendered) === 1) {
            throw new PluginException('The template contains unsupported expressions.');
        }
        return $rendered;
    }

    /** @param array<string, mixed> $context */
    private function value(array $context, string $path): mixed
    {
        $value = $context;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return '';
            }
            $value = $value[$segment];
        }
        return is_scalar($value) || $value instanceof SafeHtml ? $value : '';
    }
}
