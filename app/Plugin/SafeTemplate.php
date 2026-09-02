<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

use ReaCms\Support\PlainText;

final class SafeTemplate
{
    /** @param array<string, mixed> $context */
    public function render(string $template, array $context): string
    {
        return $this->renderBindings($template, $context, static function (mixed $value, bool $html): string {
            $string = self::string($value);
            if ($html) {
                return $value instanceof SafeHtml ? $value->value : SafeHtml::sanitize($string)->value;
            }

            return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        });
    }

    /** @param array<string, mixed> $context */
    public function renderText(string $template, array $context): string
    {
        $rendered = $this->renderBindings(
            $template,
            $context,
            static fn (mixed $value, bool $html): string => PlainText::fromHtml(self::string($value)),
        );

        return PlainText::fromHtml($rendered);
    }

    /**
     * @param array<string, mixed> $context
     * @param callable(mixed, bool): string $replace
     */
    private function renderBindings(string $template, array $context, callable $replace): string
    {
        $this->assertSafe($template);
        $path = '[a-z][a-z0-9_-]*(?:\.[a-z][a-z0-9_]*)*';
        $rendered = preg_replace_callback(
            '/\{\{\s*(' . $path . ')\s*(?:\|\s*(sanitized_html))?\s*\}\}'
                . '|\{\s*(' . $path . ')\s*(?:\|\s*(sanitized_html))?\s*\}/i',
            function (array $matches) use ($context, $replace): string {
                $captures = array_pad($matches, 5, '');
                $path = $captures[1] !== '' ? $captures[1] : $captures[3];
                $filter = $captures[2] !== '' ? $captures[2] : $captures[4];

                return $replace($this->value($context, $path), $filter === 'sanitized_html');
            },
            $template,
        );
        if (
            $rendered === null || preg_match('/\{\{|\}\}/', $rendered) === 1
            || preg_match('/\{\s*[a-z][a-z0-9_.-]*\s*(?:\(|\|)/i', $rendered) === 1
        ) {
            throw new PluginException('The template contains unsupported expressions.');
        }

        return $rendered;
    }

    private function assertSafe(string $template): void
    {
        if (
            str_contains($template, '<?') || str_contains($template, '{%')
            || preg_match('/<\s*(?:script|iframe|object|embed|applet|base|meta)\b/i', $template) === 1
            || preg_match('/\s+on[a-z]+\s*=/i', $template) === 1
            || preg_match('/\b(?:href|src)\s*=\s*(["\'])?\s*(?:javascript|vbscript|data):/i', $template) === 1
        ) {
            throw new PluginException('The template contains executable syntax.');
        }
    }

    private static function string(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? (string) $value : '';
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
        return is_scalar($value) || $value instanceof SafeHtml || $value === null ? $value : '';
    }
}
